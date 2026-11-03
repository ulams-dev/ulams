<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\Models\Version;
use Ulams\Courses\Tests\Models\User;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Tests\TestCase;

/**
 * Restyle proposals (ADR 0196, SG-5): after a style profile changes, an optional proposal of kind `style` with no source
 * revision: one item per element the style check flags, analysed by `style_restyle`, reviewed as a diff, applied only when
 * the author says so. Rejecting it leaves the course as it was.
 */
class StyleProposalTest extends TestCase
{
    private const P = '/api/admin/course-builder/style-profiles';

    /** @return array{0:User,1:Session} a course applied, then a profile approved that bans "coffee" and prefers "brew" for "coffee beans" */
    private function appliedWithProfile(bool $approve = true): array
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->profile($approve);

        return [$author, $session->refresh()];
    }

    private function profile(bool $approve = true, string $name = 'Plain'): string
    {
        $id = $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => $name, 'profile' => [
            'banned' => ['blocking' => false, 'phrases' => [['phrase' => 'coffee', 'citations' => []]]],
            'voice' => ['person' => 'you', 'citations' => []],
        ]])->assertCreated()->json('data.id');
        if ($approve) {
            $this->actingAs($this->admin(), 'api')->postJson(self::P . "/{$id}/approve")->assertOk();
        }

        return $id;
    }

    private function uri(Session $session): string
    {
        return "/api/admin/living-course/sessions/{$session->id}/style-proposals";
    }

    private function create(User $author, Session $session, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($author, 'api')->postJson($this->uri($session), $body);
    }

    private function started(User $author, Session $session, array $body = []): Proposal
    {
        $this->create($author, $session, $body + ['confirmEstimate' => true])->assertStatus(201);

        return Proposal::query()->where('session_id', $session->id)->where('kind', 'style')->latest('number')->firstOrFail();
    }

    private function text(Session $session): string
    {
        return json_encode(Version::query()->findOrFail($session->refresh()->current_version_id)->document, JSON_UNESCAPED_UNICODE);
    }

    public function testWithoutConfirmationOnlyTheEstimateIsAnsweredAndNothingIsCreated(): void
    {
        [$author, $session] = $this->appliedWithProfile();

        $data = $this->create($author, $session)->assertOk()->json('data');

        $this->assertSame('estimate', $data['state']);
        $this->assertSame('Plain', $data['profile']['name']);
        $this->assertGreaterThan(0, $data['elements']);
        $this->assertGreaterThanOrEqual($data['elements'], $data['findings']);
        $this->assertGreaterThan(0, $data['estimateMicroUsd']);
        $this->assertSame(0, Proposal::query()->where('kind', 'style')->count());
    }

    public function testAConfirmedRestyleBecomesAReviewableProposalOfItsOwnKindWithNoSourceRevision(): void
    {
        [$author, $session] = $this->appliedWithProfile();
        $revisions = Revision::query()->count();
        $before = $this->text($session);
        $this->assertStringContainsString('offee', $before);

        $proposal = $this->started($author, $session);

        $this->assertSame('style', $proposal->kind);
        $this->assertSame('style', $proposal->trigger);
        $this->assertNull($proposal->source_id);
        $this->assertNull($proposal->to_revision_id);
        $this->assertSame($session->current_version_id, $proposal->base_version_id);
        $this->assertSame($revisions, Revision::query()->count());
        $this->assertSame('Plain', $proposal->counts['styleProfileName']);
        $this->assertNotEmpty($proposal->evidence['findings']);
        $this->assertSame('ready', $proposal->status, (string) $proposal->error);

        $items = ProposalItem::query()->where('proposal_id', $proposal->id)->get();
        $this->assertGreaterThan(0, $items->count());
        foreach ($items as $item) {
            $this->assertSame('update', $item->kind, "{$item->element_id}: {$item->reason}");
            $this->assertNotEmpty($item->fragment_ids);
            $this->assertStringNotContainsStringIgnoringCase('coffee', json_encode($item->after));
            $this->assertStringContainsStringIgnoringCase('coffee', json_encode($item->before));
        }
        // the course itself is untouched until the author applies
        $this->assertSame($before, $this->text($session));

        $call = AiCall::query()->where('task', 'style_restyle')->firstOrFail();
        $this->assertSame(Proposal::SUBJECT_TYPE, $call->subject_type);
        $this->assertSame($proposal->id, $call->subject_id);
        $this->assertGreaterThan(0, $call->input_tokens);
        $this->assertNotEmpty($call->model_requested);
        $this->assertSame($author->getKey(), $call->user_id);

        $audit = AuditEntry::query()->where('action', 'proposal.created')->where('subject_id', $proposal->id)->sole();
        $this->assertSame('style', $audit->data['kind']);
        $this->assertNull($audit->revision_id);
    }

    public function testThePromptCarriesTheApprovedProfileAndTheFindingsButNeverTheStyleGuide(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $guide = $this->actingAs($author, 'api')->post(self::P, ['file' => $this->fixture('style-guide.md')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->actingAs($this->admin(), 'api')->postJson(self::P . "/{$guide}/approve")->assertOk();
        // the guide bans "obviously" and "simply" and wants "learner": make the course say them
        $version = Version::query()->findOrFail($session->current_version_id);
        $doc = $version->document;
        $doc['modules'][0]['lessons'][0]['blocks'][0]['markdown'] .= ' Simply ask each student.';
        $version->document = $doc;
        $version->save();

        $this->started($author, $session->refresh(), ['styleProfileId' => $guide]);

        $request = collect($this->fake()->sent())->first(fn ($r) => $r->task === 'style_restyle');
        $all = $request->system . implode("\n", array_map(fn ($b) => $b->text, $request->blocks));
        $this->assertStringContainsString('<style_profile>', $all);
        $this->assertStringContainsString('"banned":["simply","just","obviously"]', $all);
        $this->assertStringContainsString('"styleFindings"', $all);
        $this->assertStringContainsString('"use":"learner"', $all);
        $this->assertStringNotContainsString('How we write for learners', $all);
        $this->assertStringNotContainsString('Northwind Academy Style Guide', $all);
        $this->assertStringContainsString('untrusted="true"', $all);
    }

    public function testAcceptingAndApplyingWritesAVersionAndARelease(): void
    {
        [$author, $session] = $this->appliedWithProfile();
        $proposal = $this->started($author, $session);
        $before = $session->current_version_id;
        $synced = $this->connectionOf($session)->synced_revision_id;
        $revisions = Revision::query()->count();

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        $proposal->refresh();
        $this->assertSame('applied', $proposal->status);
        $version = Version::query()->findOrFail($proposal->result_version_id);
        $this->assertSame($before, $version->parent_id);
        $this->assertSame('Restyle for the style profile Plain: ' . $proposal->counts['items'] . ' changes', $version->reason);
        $this->assertSame('style', Release::query()->where('session_id', $session->id)->orderByDesc('number')->firstOrFail()->trigger);
        $this->assertSame($revisions, Revision::query()->count());
        $this->assertSame($synced, $this->connectionOf($session)->synced_revision_id);
        $changed = ProposalItem::query()->where('proposal_id', $proposal->id)->pluck('element_id')->all();
        $this->assertNotEmpty($changed);
        $this->assertStringNotContainsStringIgnoringCase('coffee', json_encode(array_map(fn ($id) => \Ulams\CourseBuilder\Blueprint\Blueprint::find($version->document, $id)['node'] ?? [], $changed)));

        $audit = AuditEntry::query()->where('action', 'proposal.applied')->where('subject_id', $proposal->id)->sole();
        $this->assertSame('style', $audit->data['kind']);
        $this->assertSame($proposal->counts['styleProfileId'], $audit->data['styleProfileId']);
        $this->assertNotEmpty($audit->ai_call_ids);
        $this->assertTrue(app(\Ulams\LivingCourse\Services\AuditLog::class)->verify()['ok']);
        $this->assertSame('', app(\Ulams\LivingCourse\Services\ProgressRules::class)->note($proposal), 'learners see the default notice, not the model reasons');

        // after the restyle the same profile finds nothing a rewrite can fix
        $this->create($author, $session->refresh())->assertStatus(422)->assertJsonPath('code', 'nothing_to_restyle');
    }

    public function testRejectingTheProposalLeavesTheCourseAsItWas(): void
    {
        [$author, $session] = $this->appliedWithProfile();
        $proposal = $this->started($author, $session);
        $before = $this->text($session);
        $current = $session->current_version_id;

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/reject")->assertOk();

        $this->assertSame('rejected', $proposal->refresh()->status);
        $this->assertSame(0, ProposalItem::query()->where('proposal_id', $proposal->id)->whereNotIn('status', ['rejected'])->count());
        $this->assertSame($before, $this->text($session));
        $this->assertSame($current, $session->refresh()->current_version_id);
        $this->assertNull($proposal->result_version_id);
        $this->assertSame(1, Release::query()->where('session_id', $session->id)->count());
        $this->assertSame('proposal.rejected', AuditEntry::query()->where('subject_id', $proposal->id)->orderByDesc('id')->firstOrFail()->action);
    }

    public function testRejectingOneItemKeepsThatElementAndAppliesTheRest(): void
    {
        [$author, $session] = $this->appliedWithProfile();
        $proposal = $this->started($author, $session);
        $items = ProposalItem::query()->where('proposal_id', $proposal->id)->orderBy('id')->get();
        if ($items->count() < 2) {
            $this->markTestSkipped('the course has a single flagged element');
        }
        $kept = $items->first();

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/items/{$kept->id}/reject")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertOk();
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertStatus(202);

        $doc = Version::query()->findOrFail($proposal->refresh()->result_version_id)->document;
        $this->assertStringContainsStringIgnoringCase('coffee', json_encode(\Ulams\CourseBuilder\Blueprint\Blueprint::find($doc, $kept->element_id)['node']));
        $applied = $items->skip(1)->first();
        $this->assertStringNotContainsStringIgnoringCase('coffee', json_encode(\Ulams\CourseBuilder\Blueprint\Blueprint::find($doc, $applied->element_id)['node']));
    }

    public function testOneOpenRestyleAtATimeAndItCannotBeStartedAgain(): void
    {
        [$author, $session] = $this->appliedWithProfile();
        $first = $this->started($author, $session);

        $data = $this->create($author, $session)->assertOk()->json('data');
        $this->assertSame('kept_existing', $data['state']);
        $this->assertSame($first->id, $data['proposal']['id']);
        $again = $this->create($author, $session, ['confirmEstimate' => true])->assertOk()->json('data');
        $this->assertSame('kept_existing', $again['state']);
        $this->assertSame(1, Proposal::query()->where('kind', 'style')->count());

        $this->actingAs($author, 'api')->getJson("/api/admin/living-course/proposals/{$first->id}")->assertOk()->assertJsonPath('data.kind', 'style');
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$first->id}/reanalyse")->assertStatus(409);
    }

    public function testRefusals(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);

        // no profile at all
        $this->create($author, $session)->assertNotFound();
        // a suggestion cannot restyle
        $draft = $this->profile(false, 'Draft');
        $this->create($author, $session, ['styleProfileId' => $draft])->assertStatus(422)->assertJsonPath('code', 'profile_not_approved');
        $this->create($author, $session, ['styleProfileId' => '01aaaaaaaaaaaaaaaaaaaaaaaa'])->assertNotFound();
        // a profile the course already follows has nothing to fix
        $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => 'Fine', 'profile' => ['banned' => ['phrases' => [['phrase' => 'zzzqqq', 'citations' => []]]]]])->assertCreated();
        $fine = StyleProfile::query()->where('name', 'Fine')->firstOrFail();
        $this->actingAs($this->admin(), 'api')->postJson(self::P . "/{$fine->id}/approve")->assertOk();
        $this->create($author, $session, ['styleProfileId' => $fine->id])->assertStatus(422)->assertJsonPath('code', 'nothing_to_restyle');
        $this->assertSame(0, Proposal::query()->where('kind', 'style')->count());
    }

    public function testWithAiSwitchedOffThereIsNothingToPropose(): void
    {
        [$author, $session] = $this->appliedWithProfile();
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);

        $this->create($author, $session->refresh())->assertStatus(503)->assertJsonPath('code', 'ai_disabled');
        $this->assertSame(0, Proposal::query()->where('kind', 'style')->count());
    }

    public function testACourseWithoutAnApprovedVersionCannotBeRestyled(): void
    {
        $author = $this->author();
        $session = $this->newSession($author);
        $profile = $this->profile();
        $this->create($author, $session, ['styleProfileId' => $profile])->assertStatus(409)->assertJsonPath('code', 'no_course');
    }

    public function testIsolation(): void
    {
        [$author, $session] = $this->appliedWithProfile();
        $uri = $this->uri($session);

        foreach ([[$this->tutor(), 403], [$this->student(), 403]] as [$user, $status]) {
            $this->actingAs($user, 'api')->postJson($uri, ['confirmEstimate' => true])->assertStatus($status);
        }
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/sessions/01aaaaaaaaaaaaaaaaaaaaaaaa/style-proposals")->assertNotFound();
        $this->actingAs($author, 'api')->postJson('/api/admin/living-course/sessions/not-an-id/style-proposals')->assertNotFound();
        $this->assertSame(0, Proposal::query()->where('kind', 'style')->count());

        // another author cannot decide or apply the proposal either
        $proposal = $this->started($author, $session);
        $other = $this->tutor();
        $this->actingAs($other, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/accept-all")->assertForbidden();
        $this->actingAs($other, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/apply")->assertForbidden();
        $this->actingAs($other, 'api')->getJson("/api/admin/living-course/proposals/{$proposal->id}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson($uri)->assertStatus(401);
    }
}
