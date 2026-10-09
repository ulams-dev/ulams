<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\Usage;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Pipeline\GenerationService;
use Ulams\CourseBuilder\Tests\TestCase;

/** Gates, retries, resumability, budgets and stale surfaces. */
class WorkflowTest extends TestCase
{
    public function testRejectingTheOutlineRegeneratesWithTheComment(): void
    {
        $author = $this->author();
        $session = $this->toOutline($author);
        $first = $session->currentVersion;

        $this->action($author, $session, 'reject_outline', "outline-{$first->id}", ['versionId' => $first->id, 'comment' => 'Fewer modules please'])->assertStatus(202);
        $session->refresh();
        $second = $session->currentVersion;
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(Version::REJECTED, $first->refresh()->status);
        $this->assertSame($first->id, $second->parent_id);
        $this->assertSame('Fewer modules please', $second->reason);
        $outlineCall = collect($this->fake()->sent())->last(fn ($r) => $r->task === 'outline');
        $this->assertStringContainsString('<author_feedback>Fewer modules please</author_feedback>', $outlineCall->blocks[count($outlineCall->blocks) - 1]->text);
    }

    public function testObjectivesCanBeEditedInlineBeforeApproval(): void
    {
        $author = $this->author();
        $session = $this->toOutline($author);
        $proposal = $session->currentVersion;
        $objective = $proposal->document['modules'][0]['lessons'][0]['objectives'][0];

        $this->action($author, $session, 'approve_outline', "outline-{$proposal->id}", [
            'versionId' => $proposal->id,
            'edits' => [['objectiveId' => $objective['id'], 'text' => 'Calculate the water for any dose']],
        ])->assertStatus(202);

        $edited = Version::query()->where('session_id', $session->id)->where('kind', 'outline')->where('origin', 'author')->firstOrFail();
        $this->assertSame('Calculate the water for any dose', $edited->document['modules'][0]['lessons'][0]['objectives'][0]['text']);
        $this->assertSame(Version::APPROVED, $proposal->refresh()->status);
        $this->assertSame($edited->id, Run::query()->where('session_id', $session->id)->where('kind', 'generate')->first()->input['outlineVersionId']);
    }

    public function testAFailedLessonCanBeRetriedAloneWhileTheRestIsKept(): void
    {
        $author = $this->author();
        $session = $this->toOutline($author);
        // the first lesson call is refused, the others use the synthetic answers
        $this->fake()->queue('lesson', new DriverResponse('', 'fake-model', 'refusal', new Usage(10, 0), 'r', 'cyber'));
        $proposal = $session->currentVersion;
        $this->action($author, $session, 'approve_outline', "outline-{$proposal->id}", ['versionId' => $proposal->id])->assertStatus(202);

        $run = Run::query()->where('session_id', $session->id)->where('kind', 'generate')->firstOrFail();
        $this->assertSame('needs_attention', $run->status);
        $failed = Step::query()->where('run_id', $run->id)->where('status', 'failed')->get();
        $this->assertCount(1, $failed);
        $this->assertTrue(Step::query()->where('run_id', $run->id)->where('stage', 'lessons')->where('status', 'done')->exists());
        $this->assertSame(Session::GENERATING, $session->refresh()->status);
        $done = Step::query()->where('run_id', $run->id)->where('status', 'done')->pluck('id')->all();

        $this->action($author, $session, 'retry_step', "progress-{$run->id}", ['stepId' => $failed[0]->id])->assertStatus(202);
        $this->assertSame('finished', $run->refresh()->status);
        $this->assertSame(Session::APPLY_REVIEW, $session->refresh()->status);
        // steps that were done were not run again
        foreach ($done as $id) {
            $this->assertSame(1, Step::query()->find($id)->attempts);
        }
    }

    public function testADoneStepIsSkippedOnResume(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $step = Step::query()->whereIn('run_id', Run::query()->where('session_id', $session->id)->select('id'))->where('key', 'like', 'lesson:%')->firstOrFail();
        $calls = count($this->fake()->sent());

        app(GenerationService::class)->runStep($step);

        $this->assertSame($calls, count($this->fake()->sent()));
        $this->assertSame(1, $step->refresh()->attempts);
    }

    public function testBudgetExhaustionStopsCleanly(): void
    {
        $author = $this->author();
        $session = $this->toOutline($author);
        $session->forceFill(['budget_micro_usd' => 1])->save();
        $proposal = $session->currentVersion;
        $this->action($author, $session, 'approve_outline', "outline-{$proposal->id}", ['versionId' => $proposal->id])->assertStatus(202);

        $session->refresh();
        $this->assertTrue($session->stateValue('budgetReached'));
        $run = Run::query()->where('session_id', $session->id)->where('kind', 'generate')->firstOrFail();
        $this->assertSame('needs_attention', $run->status);
        $this->assertStringContainsString('Budget reached', Step::query()->where('run_id', $run->id)->where('status', 'failed')->first()->error);
        $this->assertNotSame(Session::APPLY_REVIEW, $session->status);
    }

    public function testStaleOrUnknownSurfacesAreRefusedWithAText(): void
    {
        $author = $this->author();
        $session = $this->toOutline($author);

        $this->action($author, $session, 'answer', 'interview', ['key' => 'tone', 'value' => 'academic'])->assertStatus(202)->assertJsonPath('data.accepted', false);
        $this->action($author, $session, 'approve_outline', 'outline-01aaaaaaaaaaaaaaaaaaaaaaaa', ['versionId' => '01aaaaaaaaaaaaaaaaaaaaaaaa'])->assertJsonPath('data.accepted', false);
        $this->assertStringContainsString('out of date', Event::query()->where('session_id', $session->id)->where('type', 'TEXT_MESSAGE_CONTENT')->latest('id')->first()->payload['delta']);
        $this->action($author, $session, 'delete_everything', 'interview')->assertStatus(422);
    }

    public function testNothingIsAppliedWithoutTheApplyApproval(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $this->assertNull($session->course_id);
        $this->assertSame(Version::PROPOSED, $session->currentVersion->status);
        $this->assertSame(0, \Ulams\CourseBuilder\Models\EntityMapEntry::query()->where('session_id', $session->id)->count());
    }

    public function testBriefEditAfterTheOutlineMarksStagesStale(): void
    {
        $author = $this->author();
        $session = $this->toOutline($author);
        $this->actingAs($author, 'api')->putJson("/api/admin/course-builder/sessions/{$session->id}/brief", ['brief' => ['tone' => 'academic']])
            ->assertOk()->assertJsonPath('data.stale', true)->assertJsonPath('data.brief.decidedBy.tone', 'author');
        $this->actingAs($author, 'api')->putJson("/api/admin/course-builder/sessions/{$session->id}/brief", ['brief' => ['tone' => 'sarcastic']])->assertStatus(422);
    }

    public function testDailySessionLimit(): void
    {
        config(['course_builder.limits.sessions_per_author_per_day' => 1]);
        $author = $this->author();
        $this->actingAs($author, 'api')->postJson('/api/admin/course-builder/sessions')->assertCreated();
        $this->actingAs($author, 'api')->postJson('/api/admin/course-builder/sessions')->assertStatus(429);
    }
}
