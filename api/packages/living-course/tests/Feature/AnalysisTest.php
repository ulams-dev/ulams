<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Exceptions\DriverException;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Tests\TestCase;

/** AI analysis of an update proposal on the fake driver (plan 8.2, 8.3, 10.4). */
class AnalysisTest extends TestCase
{
    private function upload($author, Session $session, string $fixture = 'coffee-brewing.v2.md'): TestResponse
    {
        return $this->actingAs($author, 'api')->post("/api/admin/living-course/sources/{$this->sourceOf($session)->id}/revisions", ['file' => $this->lcFixture($fixture)]);
    }

    private function proposal(Session $session): Proposal
    {
        return Proposal::query()->where('session_id', $session->id)->orderByDesc('number')->firstOrFail();
    }

    public function testSmallProposalsAreAnalysedAutomaticallyOneCallPerLessonGroup(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session)->assertCreated();

        $proposal = $this->proposal($session);
        $this->assertSame('ready', $proposal->status);
        $this->assertGreaterThan(0, $proposal->estimated_cost_micro_usd);
        $this->assertGreaterThan(0, $proposal->cost_micro_usd);

        $run = Run::query()->findOrFail($proposal->run_id);
        $this->assertSame('sync', $run->kind);
        $this->assertSame('finished', $run->status);
        $groups = Step::query()->where('run_id', $run->id)->get();
        $this->assertCount(5, $groups, 'four lessons and the course objectives');
        $this->assertSame(['done'], $groups->pluck('status')->unique()->values()->all());

        // one update call per group; grounding checks for groups with new blocks; all logged against the proposal
        $calls = AiCall::query()->forSubject('living_course_proposal', $proposal->id)->get();
        $this->assertSame(5, $calls->where('task', 'update')->count());
        $this->assertSame(3, $calls->where('task', 'grounding')->count());
        $this->assertSame($proposal->cost_micro_usd, (int) $calls->sum('cost_micro_usd'));
        $this->assertNotNull($calls->first()->model_requested);

        $items = ProposalItem::query()->where('proposal_id', $proposal->id)->get();
        $this->assertSame(0, $items->where('kind', 'manual')->count());
        $this->assertSame(['no_change' => 6, 'remove' => 4, 'update' => 12], $items->whereIn('kind', ['update', 'no_change', 'remove'])->countBy('kind')->sortKeys()->all());
        $this->assertSame(['accepted'], $items->where('kind', 'citation_remap')->pluck('status')->unique()->values()->all());
        $this->assertSame(['pending'], $items->whereIn('kind', ['update', 'no_change', 'remove'])->pluck('status')->unique()->values()->all(), 'the author decides everything');

        $block = $items->first(fn ($i) => $i->kind === 'update' && $i->element_type === 'block' && str_starts_with((string) $i->label, 'Lesson 2.1'));
        $this->assertStringContainsString('1:15', $block->after['markdown']);
        $this->assertNotSame($block->before['markdown'], $block->after['markdown']);
        $this->assertSame($block->before['id'], $block->after['id'], 'the element id is kept');
        $this->assertSame('major', $block->change_class);
        $this->assertNotEmpty($block->ai_call_ids);

        $removed = $items->where('kind', 'remove');
        $this->assertSame(['removed'], $removed->pluck('change_class')->unique()->values()->all());
        $this->assertSame(['Lesson 4.2'], $removed->map(fn ($i) => explode(' › ', (string) $i->label)[0])->unique()->values()->all());
        $this->assertSame('proposal.analysed', AuditEntry::query()->where('subject_id', $proposal->id)->where('action', 'proposal.analysed')->firstOrFail()->action);
    }

    public function testChangedAnswersAreDetectedByCodeAndKeepTheAnswerCheckMarker(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->upload($author, $session)->assertCreated();

        $questions = ProposalItem::query()->where('proposal_id', $this->proposal($session)->id)->where('element_type', 'question')->where('kind', 'update')->get();
        $this->assertCount(6, $questions);
        $this->assertSame(['answer_changed'], $questions->pluck('change_class')->unique()->values()->all());
        $this->assertSame(['changed'], $questions->pluck('answer_status')->unique()->values()->all());
        $this->assertSame([true], $questions->pluck('answer_check')->unique()->values()->all());
        $q = $questions->first(fn ($i) => str_starts_with((string) $i->label, 'Lesson 2.1'));
        $correct = collect($q->after['options'])->firstWhere('correct', true);
        $this->assertStringContainsString('1:15', $correct['text']);
        $this->assertNotSame(collect($q->before['options'])->firstWhere('correct', true)['text'], $correct['text']);
        $this->assertCount(3, $q->after['options']);
    }

    public function testTheSessionCostMeterIncludesTheAnalysis(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $before = Llm::cost($session)['usedMicroUsd'];
        $this->upload($author, $session)->assertCreated();

        $this->assertSame($before + $this->proposal($session)->cost_micro_usd, Llm::cost($session)['usedMicroUsd']);
    }

    public function testProposalsAboveTheAutomaticThresholdWaitForAConfirmedEstimate(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        config(['living_course.cost.auto_analyse_usd' => 0.000001]);
        $this->upload($author, $session)->assertCreated();
        $proposal = $this->proposal($session);

        $this->assertSame('awaiting_analysis', $proposal->status);
        $this->assertGreaterThan(0, $proposal->estimated_cost_micro_usd);
        $this->assertSame(0, AiCall::query()->forSubject('living_course_proposal', $proposal->id)->count());
        $url = "/api/admin/living-course/proposals/{$proposal->id}/analyse";

        $needs = $this->actingAs($author, 'api')->postJson($url)->assertStatus(409);
        $this->assertSame('confirm_estimate', $needs->json('code'));
        $this->assertGreaterThan(0, $needs->json('data.estimateMicroUsd'));
        $this->assertSame('awaiting_analysis', $proposal->refresh()->status);

        $this->actingAs($author, 'api')->postJson($url, ['confirmEstimate' => true])->assertStatus(202);
        $this->assertSame('ready', $proposal->refresh()->status);
        $this->assertGreaterThan(0, AiCall::query()->forSubject('living_course_proposal', $proposal->id)->count());
    }

    public function testTheProposalCapAndTheMonthlySourceBudgetBlockTheAnalysis(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        config(['living_course.cost.proposal_usd' => 0.000001]);
        $this->upload($author, $session)->assertCreated();
        $proposal = $this->proposal($session);

        $this->assertSame('budget_blocked', $proposal->status);
        $this->assertStringContainsString('limit per proposal', (string) $proposal->error);
        $this->assertSame(0, AiCall::query()->forSubject('living_course_proposal', $proposal->id)->count());
        $this->assertSame('budget_blocked', $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/analyse", ['confirmEstimate' => true])->assertStatus(422)->json('code'));

        // the manual items stay: the author can still update by hand
        $this->assertGreaterThan(0, ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'manual')->count());

        config(['living_course.cost.proposal_usd' => 5, 'living_course.cost.source_monthly_usd' => 0.000001]);
        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/analyse", ['confirmEstimate' => true])->assertStatus(422);
        $this->assertStringContainsString('monthly', (string) $proposal->refresh()->error);
    }

    public function testAnalysisIsRefusedWithAiDisabledButTheProposalStaysUsable(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        config(['living_course.cost.auto_analyse_usd' => 0]);
        $this->upload($author, $session)->assertCreated();
        $proposal = $this->proposal($session);
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(LlmClient::class);
        $this->app->forgetInstance(LlmDriver::class);
        $this->app->forgetInstance(\Ulams\LivingCourse\Http\Controllers\ProposalsController::class);

        $this->actingAs($author, 'api')->postJson("/api/admin/living-course/proposals/{$proposal->id}/analyse")->assertStatus(503)->assertJsonPath('code', 'ai_disabled');
        $this->actingAs($author, 'api')->getJson("/api/admin/living-course/proposals/{$proposal->id}")->assertOk();
    }

    public function testAFailedGroupIsRetriedAloneAndTheRestIsKept(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->fake()->queue('update', fn () => throw new DriverException('provider hiccup'));
        $this->upload($author, $session)->assertCreated();
        $proposal = $this->proposal($session);

        $run = Run::query()->findOrFail($proposal->run_id);
        $this->assertSame('needs_attention', $run->status);
        $failed = Step::query()->where('run_id', $run->id)->where('status', 'failed')->get();
        $this->assertCount(1, $failed);
        $this->assertSame(4, Step::query()->where('run_id', $run->id)->where('status', 'done')->count());
        $this->assertSame('ready', $proposal->status, 'the finished groups can be reviewed');
        $this->assertSame([substr($failed->first()->key, 6)], $proposal->counts['failedGroups']);
        $manual = ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'manual')->count();
        $this->assertGreaterThan(0, $manual);

        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/runs/{$run->id}/steps/{$failed->first()->id}/retry")->assertStatus(202);

        $this->assertSame('finished', $run->refresh()->status);
        $this->assertSame(0, ProposalItem::query()->where('proposal_id', $proposal->id)->where('kind', 'manual')->count());
        $this->assertSame([], $proposal->refresh()->counts['failedGroups']);
    }

    public function testUnsupportedBlocksTriggerOneRegenerationAndThenAFlag(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $unsupported = ['unsupported' => [['blockIndex' => 0, 'claim' => 'Pour at 99 degrees', 'reason' => 'The source says 96.']]];
        // every grounding check of the first lesson group finds a problem: the regeneration does not help either
        $this->fake()->queueJson('grounding', $unsupported)->queueJson('grounding', $unsupported);
        $this->upload($author, $session)->assertCreated();
        $proposal = $this->proposal($session);

        $flagged = ProposalItem::query()->where('proposal_id', $proposal->id)->get()->filter(fn ($i) => isset($i->flags['grounding']));
        $this->assertCount(1, $flagged);
        $this->assertStringContainsString('Possibly unsupported: Pour at 99 degrees', $flagged->first()->flags['grounding'][0]);
        // 5 groups + 1 regeneration of the flagged one
        $this->assertSame(6, AiCall::query()->forSubject('living_course_proposal', $proposal->id)->where('task', 'update')->count());
    }

    public function testTheModelNeverSeesCommitMessagesOrSecretsOnlyEscapedSourceText(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $connection = $this->connectionOf($session);
        $connection->forceFill(['secrets' => ['token' => 'ghp_verysecret'], 'last_error' => 'internal failure detail'])->save();
        $this->upload($author, $session)->assertCreated();

        $sent = collect($this->fake()->sent())->where('task', 'update');
        $this->assertGreaterThan(0, $sent->count());
        foreach ($sent as $request) {
            $all = $request->system . implode("\n", array_map(fn ($b) => $b->text, $request->blocks));
            $this->assertStringNotContainsString('verysecret', $all);
            $this->assertStringNotContainsString('internal failure detail', $all);
        }
        // cache breakpoints: the shared blocks are identical across groups
        $first = $sent->first()->blocks;
        $second = $sent->skip(1)->first()->blocks;
        $this->assertSame($first[0]->text, $second[0]->text);
        $this->assertSame($first[1]->text, $second[1]->text);
        $this->assertNotSame($first[2]->text, $second[2]->text);
    }
}
