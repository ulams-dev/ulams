<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\AuthoringMetrics\Services\StageTimingRecorder;
use Ulams\AuthoringMetrics\Tests\TestCase;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Run;

/** Live recording: the Course Builder dispatches stage events, this package turns them into rows. */
class StageTimingsTest extends TestCase
{
    public function testAFullBuildRecordsOutlineLessonsQuizzesAndApply(): void
    {
        $session = $this->toApplied($this->author(), 'coffee-brewing.md', ['quiz', 'final']);

        $rows = StageTiming::query()->where('session_id', $session->id)->get();
        $byKind = $rows->groupBy('run_kind');
        $this->assertTrue($byKind->has('outline'), 'the outline run has no steps and becomes one row named after its kind');
        $this->assertSame(['outline'], $byKind['outline']->pluck('stage')->all());
        $generated = $byKind['generate']->pluck('stage')->all();
        foreach (['lessons', 'quizzes', 'metadata'] as $stage) {
            $this->assertContains($stage, $generated);
        }
        $this->assertContains('apply', $byKind['apply']->pluck('stage')->all());
        foreach ($rows as $row) {
            $this->assertSame(StageTiming::EXACT, $row->quality, "{$row->run_kind}/{$row->stage}");
            $this->assertNotNull($row->finished_at);
            $this->assertGreaterThanOrEqual(0.0, (float) $row->seconds);
            $this->assertEqualsWithDelta($row->finished_at->getTimestampMs() - $row->started_at->getTimestampMs(), (float) $row->seconds * 1000, 1.0);
        }
        // every timed run is a real run of this session
        $this->assertSame([], $rows->pluck('run_id')->unique()->diff(Run::query()->where('session_id', $session->id)->pluck('id'))->all());
    }

    public function testTimingsOutliveThePrunedEvents(): void
    {
        $session = $this->toApplied($this->author());
        $before = StageTiming::query()->where('session_id', $session->id)->count();
        $this->assertGreaterThan(3, $before);

        config(['course_builder.events_retention_days' => 0]);
        \Illuminate\Support\Carbon::setTestNow(now()->addMinute());
        $this->artisan('course-builder:prune-events')->assertSuccessful();
        \Illuminate\Support\Carbon::setTestNow();

        $this->assertSame(0, \Ulams\CourseBuilder\Models\Event::query()->where('session_id', $session->id)->count());
        $this->assertSame($before, StageTiming::query()->where('session_id', $session->id)->count());
    }

    public function testAStageStartedTwiceByARetryKeepsBothAttempts(): void
    {
        $session = $this->bareSession();
        $run = $this->run_($session, 'generate', 'running', $this->t('10:00:00'), null);
        $recorder = app(StageTimingRecorder::class);
        $recorder->stageStarted($session->id, $run->id, 'generate', 'lessons', $this->t('10:00:00'));
        $recorder->stageFinished($run->id, 'lessons', $this->t('10:00:30'));
        $recorder->stageStarted($session->id, $run->id, 'generate', 'lessons', $this->t('10:05:00'));
        $recorder->stageFinished($run->id, 'lessons', $this->t('10:05:12.500'));

        $this->assertSame(['30.000', '12.500'], StageTiming::query()->where('run_id', $run->id)->orderBy('started_at')->pluck('seconds')->all());
    }

    public function testACrashMidStageClosesItWithTheRunAndFlagsItIncomplete(): void
    {
        $session = $this->bareSession();
        $run = $this->run_($session, 'generate', 'running', $this->t('10:00:00'), null);
        $recorder = app(StageTimingRecorder::class);
        $recorder->stageStarted($session->id, $run->id, 'generate', 'lessons', $this->t('10:00:00'));
        $recorder->stageFinished($run->id, 'lessons', $this->t('10:01:00'));
        $recorder->stageStarted($session->id, $run->id, 'generate', 'quizzes', $this->t('10:01:00'));

        $recorder->runEnded($run, $this->t('10:01:20'), false);

        $rows = StageTiming::query()->where('run_id', $run->id)->orderBy('started_at')->get();
        $this->assertSame([StageTiming::EXACT, StageTiming::INCOMPLETE], $rows->pluck('quality')->all());
        $this->assertSame('20.000', $rows[1]->seconds);
        $this->assertSame($this->t('10:01:20')->getTimestampMs(), $rows[1]->finished_at->getTimestampMs());
    }

    public function testACancelledRunWithoutStagesIsOneIncompleteRow(): void
    {
        $session = $this->bareSession();
        $run = $this->run_($session, 'outline', 'running', $this->t('10:00:00'), null);
        $this->logged($run, 'RUN_STARTED', $this->t('10:00:00'));

        app(StageTimingRecorder::class)->runEnded($run, $this->t('10:00:08'), false);

        $row = StageTiming::query()->where('run_id', $run->id)->sole();
        $this->assertSame(['outline', StageTiming::INCOMPLETE, '8.000'], [$row->stage, $row->quality, $row->seconds]);
    }

    public function testAFailingRecorderNeverBreaksTheBuild(): void
    {
        $this->app->bind(StageTimingRecorder::class, fn () => new class extends StageTimingRecorder {
            public function stageStarted(string $sessionId, string $runId, string $runKind, string $stage, \Carbon\CarbonImmutable $at): void
            {
                throw new \RuntimeException('database is gone');
            }
        });
        $session = $this->bareSession();
        $run = $this->run_($session, 'generate', 'running', now(), null);

        app(EventLog::class)->stepStarted($run, 'lessons');

        $this->assertSame(0, StageTiming::query()->count());
        $this->assertDatabaseHas('course_builder_events', ['run_id' => $run->id, 'type' => 'STEP_STARTED']);
    }
}
