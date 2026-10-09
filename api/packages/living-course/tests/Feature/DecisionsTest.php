<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Events\ElementPatched;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\ElementStatus;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Tests\TestCase;

/** Item decisions, accept all, reject all and regeneration (plan 8.3). */
class DecisionsTest extends TestCase
{
    /** @return array{0:\Ulams\Courses\Tests\Models\User,1:Session,2:Proposal} */
    private function ready(): array
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertCreated();

        return [$author, $session, Proposal::query()->where('session_id', $session->id)->firstOrFail()];
    }

    private function url(Proposal $p, string $path = ''): string
    {
        return "/api/admin/living-course/proposals/{$p->id}{$path}";
    }

    public function testItemsAreAcceptedRejectedAndResetAndEveryDecisionIsAudited(): void
    {
        [$author, , $proposal] = $this->ready();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->first();

        $accepted = $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/accept"))->assertOk()->json('data');
        $this->assertSame('accepted', $accepted['item']['status']);
        $this->assertSame(1, $accepted['proposal']['decisions']['accepted'] - ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'citation_remap')->count());
        $this->assertSame((int) $author->getKey(), $item->refresh()->decided_by);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/reject"))->assertOk()->assertJsonPath('data.item.status', 'rejected');
        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/reset"))->assertOk()->assertJsonPath('data.item.status', 'pending');
        $this->assertNull($item->refresh()->decided_by);

        $actions = AuditEntry::query()->where('subject_id', $item->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame(['item.accepted', 'item.rejected', 'item.reset'], $actions);
        $entry = AuditEntry::query()->where('subject_id', $item->id)->first();
        $this->assertSame($item->element_id, $entry->data['element']);
        $this->assertSame($proposal->to_revision_id, $entry->revision_id);
    }

    public function testAcceptAllTakesEveryUndecidedItem(): void
    {
        [$author, , $proposal] = $this->ready();
        $first = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->first();
        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$first->id}/reject"))->assertOk();

        $response = $this->actingAs($author, 'api')->postJson($this->url($proposal, '/accept-all'))->assertOk();

        $this->assertSame(21, $response->json('data.accepted'), 'the rejected one keeps its decision');
        $this->assertSame('rejected', $first->refresh()->status);
        $this->assertSame(0, ProposalItem::query()->where('proposal_id', $proposal->id)->where('status', 'pending')->whereIn('kind', ['update', 'no_change', 'remove'])->count());
    }

    public function testRejectingTheWholeProposalAcknowledgesTheRevision(): void
    {
        [$author, $session, $proposal] = $this->ready();
        $source = $this->sourceOf($session);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/reject'))->assertOk()->assertJsonPath('data.status', 'rejected');

        $connection = $this->connectionOf($session);
        $this->assertSame($proposal->to_revision_id, $connection->synced_revision_id, 'the synced pointer advanced');
        $this->assertStringContainsString('1:15', Fragment::query()->where('source_id', $source->id)->get()->pluck('text')->implode(' '));
        $this->assertSame(0, ElementStatus::query()->where('session_id', $session->id)->whereIn('status', ['pending', 'source_removed'])->count());
        $this->assertSame(22, ElementStatus::query()->where('session_id', $session->id)->where('status', 'dismissed')->count());
        $this->assertSame('dismissed', $this->actingAs($author, 'api')->getJson("/api/admin/living-course/sessions/{$session->id}/staleness")->json('data.summary.state'));
        $this->assertSame('proposal.rejected', AuditEntry::query()->where('subject_id', $proposal->id)->where('action', 'proposal.rejected')->firstOrFail()->action);
        $this->assertSame(0, ProposalItem::query()->where('proposal_id', $proposal->id)->where('status', 'pending')->count());

        // the same file again changes nothing, and a later edit is compared with the acknowledged revision
        $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$source->id}/revisions", ['file' => $this->lcFixture('coffee-brewing.v2.md')])->assertOk();
        $this->assertSame(1, Proposal::query()->where('session_id', $session->id)->count());
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/reject'))->assertStatus(409);
    }

    public function testDecisionsNeedAnOpenProposalAndAnAuthorizedUser(): void
    {
        [$author, , $proposal] = $this->ready();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->first();
        $urls = [$this->url($proposal, "/items/{$item->id}/accept"), $this->url($proposal, "/items/{$item->id}/reject"), $this->url($proposal, "/items/{$item->id}/reset"), $this->url($proposal, "/items/{$item->id}/regenerate"), $this->url($proposal, '/accept-all'), $this->url($proposal, '/reject'), $this->url($proposal, '/analyse')];

        foreach ($urls as $url) {
            $this->actingAs($this->tutor(), 'api')->postJson($url)->assertStatus(403);
            $this->actingAs($this->student(), 'api')->postJson($url)->assertStatus(403);
        }
        $readOnly = $this->readOnlyAdmin();
        foreach ($urls as $url) {
            $this->actingAs($readOnly, 'api')->postJson($url)->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        foreach ($urls as $url) {
            $this->postJson($url)->assertStatus(401);
        }
        $this->assertSame('pending', $item->refresh()->status);

        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';
        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$missing}/accept"))->assertStatus(404);
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$missing}/accept-all")->assertStatus(404);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/reject'))->assertOk();
        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/accept"))->assertStatus(409);
        $this->actingAs($author, 'api')->postJson($this->url($proposal, '/accept-all'))->assertStatus(409);
    }

    public function testAnAdminWithTheReviewPermissionMayDecideForAnAbsentAuthor(): void
    {
        [, , $proposal] = $this->ready();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->first();
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->postJson($this->url($proposal, "/items/{$item->id}/accept"))->assertOk();

        $this->assertSame((int) $admin->getKey(), $item->refresh()->decided_by);
    }

    public function testRegenerationAsksTheModelAgainForOneElementAndIsLimited(): void
    {
        [$author, , $proposal] = $this->ready();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('element_type', 'block')->first();
        $url = $this->url($proposal, "/items/{$item->id}/regenerate");
        $callsBefore = AiCall::query()->forSubject('living_course_proposal', $proposal->id)->count();
        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/accept"))->assertOk();

        $response = $this->actingAs($author, 'api')->postJson($url, ['comment' => 'Keep it shorter and mention the ratio <b>explicitly</b>.'])->assertOk();

        $this->assertSame(1, $response->json('data.item.regenerations'));
        $this->assertSame('pending', $response->json('data.item.status'), 'a new version has to be decided again');
        $this->assertGreaterThan($callsBefore, AiCall::query()->forSubject('living_course_proposal', $proposal->id)->count());
        $request = collect($this->fake()->sent())->where('task', 'update')->last();
        $text = implode("\n", array_map(fn ($b) => $b->text, $request->blocks));
        $this->assertStringContainsString('<author_request>Keep it shorter and mention the ratio &lt;b&gt;explicitly&lt;/b&gt;.</author_request>', $text);
        $this->assertSame(1, substr_count($text, '"elementId"'), 'only that element is sent');
        $this->assertSame(1, AuditEntry::query()->where('action', 'item.regenerated')->where('subject_id', $item->id)->count());
        $this->assertSame($proposal->refresh()->cost_micro_usd, (int) AiCall::query()->forSubject('living_course_proposal', $proposal->id)->sum('cost_micro_usd'));

        $this->actingAs($author, 'api')->postJson($url)->assertOk();
        $this->actingAs($author, 'api')->postJson($url)->assertOk();
        $this->actingAs($author, 'api')->postJson($url)->assertStatus(422);
        $this->assertSame(3, $item->refresh()->regenerations);
    }

    public function testRegenerationAnswers503WithAiDisabled(): void
    {
        [$author, , $proposal] = $this->ready();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->first();
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        $this->app->forgetInstance(\Ulams\LivingCourse\Http\Controllers\ProposalsController::class);

        $this->actingAs($author, 'api')->postJson($this->url($proposal, "/items/{$item->id}/regenerate"))->assertStatus(503)->assertJsonPath('code', 'ai_disabled');
    }

    public function testAChatEditAfterTheAnalysisMarksItsItemsOutOfDate(): void
    {
        [, $session, $proposal] = $this->ready();
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('element_type', 'block')->first();
        $other = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'update')->where('id', '!=', $item->id)->first();

        event(new ElementPatched($session, $item->element_id));

        $this->assertSame('stale', $item->refresh()->status);
        $this->assertSame('pending', $other->refresh()->status);
    }
}
