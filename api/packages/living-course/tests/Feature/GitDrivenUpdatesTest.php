<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Tests\TestCase;

/**
 * Course-as-code next to Living Course (plan 9): a push waits for an open update proposal that edits the same elements, and the
 * sync pull request of a Living Course update comes back as a push that changes nothing (no second version, no second proposal).
 */
class GitDrivenUpdatesTest extends TestCase
{
    private const P = '/api/admin/course-as-code';

    /** @return array{0:\Ulams\Courses\Tests\Models\User,1:Session,2:Proposal} a course with an open proposal from a changed source */
    private function withOpenProposal(): array
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();

        return [$author, $session->refresh(), Proposal::query()->where('session_id', $session->id)->firstOrFail()];
    }

    /** @return array<string,mixed> the document with the text of one element changed */
    private function edited(array $document, string $elementId): array
    {
        $walk = function (mixed $node) use (&$walk, $elementId) {
            if (!is_array($node)) {
                return $node;
            }
            foreach ($node as $key => $value) {
                $node[$key] = $walk($value);
            }
            if (($node['id'] ?? null) === $elementId) {
                $field = isset($node['markdown']) ? 'markdown' : (isset($node['text']) ? 'text' : 'title');
                $node[$field] = $node[$field] . ' (edited in Git)';
            }

            return $node;
        };

        return $walk($document);
    }

    public function testAPushThatEditsAnElementOfAnOpenProposalWaitsForTheProposal(): void
    {
        [$author, $session, $proposal] = $this->withOpenProposal();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('status', 'pending')->firstOrFail();
        $current = $session->currentVersion;
        $versions = Version::query()->where('session_id', $session->id)->count();
        $body = ['document' => $this->edited($current->document, $item->element_id), 'baseVersionId' => $current->id];

        $response = $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", $body);

        $response->assertStatus(409)->assertJsonPath('code', 'PROPOSAL_PENDING')->assertJsonPath('data.proposals.0.proposalId', $proposal->id)->assertJsonPath('data.proposals.0.number', $proposal->number);
        $this->assertContains($item->element_id, array_column($response->json('data.proposals.0.elements'), 'elementId'));
        $this->assertSame($versions, Version::query()->where('session_id', $session->id)->count(), 'nothing is stored');

        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", $body + ['allowPending' => true])->assertCreated()->assertJsonPath('data.version.kind', 'import');
    }

    public function testAPushThatEditsOtherElementsIsNotHeldBack(): void
    {
        [$author, $session, $proposal] = $this->withOpenProposal();
        $touched = ProposalItem::query()->where('proposal_id', $proposal->id)->pluck('element_id')->all();
        $current = $session->currentVersion;
        $free = null;
        foreach ($current->document['modules'] ?? [] as $module) {
            foreach ($module['lessons'] ?? [] as $lesson) {
                foreach ($lesson['blocks'] ?? [] as $block) {
                    if (!in_array($block['id'], $touched, true) && $free === null) {
                        $free = $block['id'];
                    }
                }
            }
        }
        $this->assertNotNull($free, 'the fixture has a block no proposal item touches');

        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $this->edited($current->document, $free), 'baseVersionId' => $current->id])->assertCreated();
    }

    public function testAnAppliedProposalNoLongerHoldsPushesBack(): void
    {
        [$author, $session, $proposal] = $this->withOpenProposal();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('status', 'pending')->firstOrFail();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);
        $current = $session->refresh()->currentVersion;

        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $this->edited($current->document, $item->element_id), 'baseVersionId' => $current->id])->assertCreated();
    }

    public function testTheSyncPullRequestOfAnAppliedUpdateComesBackAsAPushThatChangesNothing(): void
    {
        [$author, $session, $proposal] = $this->withOpenProposal();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);
        $session->refresh();
        $this->assertSame('applied', $proposal->refresh()->status);
        $courseId = $session->course_id;
        $versions = Version::query()->where('session_id', $session->id)->count();
        $proposals = Proposal::query()->where('session_id', $session->id)->count();
        $revisions = DB::table('living_course_revisions')->count();

        // `ulams course pull` writes the exported document to files; merging that pull request pushes the same document back
        $export = $this->actingAs($author, 'api')->getJson(self::P . "/courses/{$courseId}/blueprint")->assertOk();
        $push = $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/imports", ['document' => $export->json('data.document'), 'baseVersionId' => $export->json('data.versionId')]);

        $push->assertOk()->assertJsonPath('data.unchanged', true)->assertJsonPath('data.version', null);
        $this->assertSame($versions, Version::query()->where('session_id', $session->id)->count());
        $this->assertSame($proposals, Proposal::query()->where('session_id', $session->id)->count());
        $this->assertSame($revisions, DB::table('living_course_revisions')->count(), 'a push never creates a source revision');
    }
}
