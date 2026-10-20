<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\Courses\Models\Topic;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Tests\TestCase;

/** A suggested lesson for a new source section no lesson covers: review as a diff, apply through the domain services. */
class SuggestLessonTest extends TestCase
{
    private function upload($author, Session $session, string $fixture): TestResponse
    {
        return $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture($fixture)]);
    }

    private function proposalOf(Session $session): Proposal
    {
        return Proposal::query()->where('session_id', $session->id)->orderByDesc('number')->firstOrFail();
    }

    private function url(Proposal $p, ProposalItem $i): string
    {
        return "/api/admin/living-course/proposals/{$p->id}/items/{$i->id}/suggest-lesson";
    }

    private function uncovered(Proposal $p): ProposalItem
    {
        return ProposalItem::query()->where('proposal_id', $p->id)->where('kind', 'uncovered')->firstOrFail();
    }

    public function testASuggestedLessonIsACitedProposalItemTheAuthorAcceptsAndApplies(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $item = $this->uncovered($proposal);
        $fragment = $item->fragment_ids[0];
        $lessonsBefore = iterator_count(Blueprint::lessons($session->currentVersion->document));

        $response = $this->actingAs($author, 'api')->postJson($this->url($proposal, $item))->assertOk();

        $this->assertSame('new_lesson', $response->json('data.item.kind'));
        $item->refresh();
        $this->assertSame('pending', $item->status, 'the author decides');
        $this->assertSame('lesson', $item->element_type);
        $this->assertNull($item->before);
        $lesson = $item->after['lesson'];
        $this->assertSame('generated', $lesson['status']);
        $this->assertNotEmpty($lesson['blocks']);
        foreach ($lesson['blocks'] as $block) {
            $this->assertSame([$fragment], $block['citations'], 'every block cites the new section');
        }
        $this->assertSame([$fragment], $lesson['citations']);
        $this->assertCount(1, $lesson['objectives']);
        $this->assertSame([$lesson['objectives'][0]['id']], $lesson['blocks'][0]['objectiveIds']);
        $this->assertNotNull($item->after['module']['id'], 'an existing module');

        // the call is logged against the proposal, the audit trail names the suggestion
        $call = AiCall::query()->forSubject('living_course_proposal', $proposal->id)->where('task', 'new_lesson')->first();
        $this->assertNotNull($call);
        $this->assertContains($call->id, $item->ai_call_ids);
        $this->assertSame(1, AuditEntry::query()->where('action', 'item.lesson_suggested')->where('subject_id', $item->id)->count());
        $this->assertSame(1, $proposal->refresh()->counts['newLessons']);

        // nothing changed in the course yet
        $this->assertSame($lessonsBefore, iterator_count(Blueprint::lessons($session->refresh()->currentVersion->document)));

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/items/{$item->id}/accept")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        $this->assertSame('applied', $proposal->refresh()->status);
        $doc = Version::query()->findOrFail($proposal->result_version_id)->document;
        $found = Blueprint::find($doc, $lesson['id']);
        $this->assertNotNull($found);
        $this->assertSame($lesson['title'], $found['node']['title']);
        $topic = EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lesson['id'])->where('entity_type', 'topic')->firstOrFail();
        $this->assertNotNull(Topic::query()->find($topic->entity_id), 'the lesson became a topic through the applier');
        $this->assertSame($proposal->id, $topic->added_by_proposal_id);
    }

    public function testRejectingTheSuggestedLessonLeavesTheCourseAsItWas(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $item = $this->uncovered($proposal);
        $this->actingAs($author, 'api')->postJson($this->url($proposal, $item))->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/items/{$item->id}/reject")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        $doc = Version::query()->findOrFail($proposal->refresh()->result_version_id)->document;
        $this->assertNull(Blueprint::find($doc, $item->refresh()->element_id));
    }

    public function testAnotherVersionCanBeRequestedWithACommentAtMostThreeTimes(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $item = $this->uncovered($proposal);
        $this->actingAs($author, 'api')->postJson($this->url($proposal, $item))->assertOk();
        $lessonId = $item->refresh()->element_id;

        for ($n = 1; $n <= 3; $n++) {
            $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/items/{$item->id}/regenerate", ['comment' => 'Shorter please'])->assertOk();
            $this->assertSame($n, $item->refresh()->regenerations);
            $this->assertSame($lessonId, $item->element_id, 'the lesson id stays stable');
        }
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/items/{$item->id}/regenerate")->assertStatus(422);
    }

    public function testOnlyUncoveredSectionsGetALesson(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $other = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->firstOrFail();

        $this->actingAs($author, 'api')->postJson($this->url($proposal, $other))->assertStatus(409);
    }

    public function testASectionAddedAloneReopensTheNoImpactProposalForALesson(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v1-storage.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $this->assertSame('no_impact', $proposal->status);
        $item = $this->uncovered($proposal);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, $item))->assertOk();

        $this->assertSame('ready', $proposal->refresh()->status);
        $this->assertSame(1, AuditEntry::query()->where('action', 'proposal.reopened')->where('subject_id', $proposal->id)->count());
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);
        $this->assertSame('applied', $proposal->refresh()->status);
        $this->assertNotNull(Blueprint::find(Version::query()->findOrFail($proposal->result_version_id)->document, $item->refresh()->element_id));
    }

    public function testAnOlderNoImpactProposalCannotBeReopened(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v1-storage.md')->assertCreated();
        $old = $this->proposalOf($session);
        $item = $this->uncovered($old);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();

        $this->actingAs($author, 'api')->postJson($this->url($old, $item))->assertStatus(409);
    }

    public function testAnotherAuthorAndAReadOnlyAdminCannotSuggest(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $item = $this->uncovered($proposal);

        $this->actingAs($this->author(), 'api')->postJson($this->url($proposal, $item))->assertForbidden();
        $this->assertSame('uncovered', $item->refresh()->kind);
        $this->assertSame(0, AiCall::query()->where('task', 'new_lesson')->count());
    }

    public function testWithoutAiThereIsNoSuggestion(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $item = $this->uncovered($proposal);
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        $this->app->forgetInstance(\Ulams\LivingCourse\Http\Controllers\ProposalsController::class);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, $item))->assertStatus(503)->assertJsonPath('code', 'ai_disabled');
    }

    public function testUnsupportedClaimsAreFlaggedOnTheLesson(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session, 'coffee-brewing.v2.md')->assertCreated();
        $proposal = $this->proposalOf($session);
        $item = $this->uncovered($proposal);
        $this->fake()->queueJson('grounding', ['unsupported' => [['blockIndex' => 0, 'claim' => 'Freeze beans for a year', 'reason' => 'The source advises against freezing.']]]);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, $item))->assertOk();

        $item->refresh();
        $this->assertStringContainsString('Freeze beans for a year', $item->flags['grounding'][0]);
        $this->assertStringContainsString('Freeze beans', $item->after['lesson']['flags'][0]);
    }
}
