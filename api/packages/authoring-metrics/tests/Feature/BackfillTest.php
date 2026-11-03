<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\AuthoringMetrics\Tests\TestCase;

class BackfillTest extends TestCase
{
    /** A finished generate run whose AG-UI events still exist, written without the live listener. */
    private function runWithEvents(): \Ulams\CourseBuilder\Models\Run
    {
        $session = $this->bareSession();
        $run = $this->run_($session, 'generate', 'finished', $this->t('09:00:00'), $this->t('09:03:00'));
        $this->logged($run, 'RUN_STARTED', $this->t('09:00:00.250'));
        $this->logged($run, 'STEP_STARTED', $this->t('09:00:00.500'), ['stepName' => 'lessons']);
        $this->logged($run, 'STEP_FINISHED', $this->t('09:02:00.500'), ['stepName' => 'lessons']);
        $this->logged($run, 'STEP_STARTED', $this->t('09:02:00.500'), ['stepName' => 'quizzes']);
        $this->logged($run, 'STEP_FINISHED', $this->t('09:02:50'), ['stepName' => 'quizzes']);
        $this->logged($run, 'RUN_FINISHED', $this->t('09:03:00'));

        return $run;
    }

    public function testEventsThatStillExistGiveExactStages(): void
    {
        $run = $this->runWithEvents();

        $this->artisan('authoring-metrics:backfill')->assertSuccessful();

        $rows = StageTiming::query()->where('run_id', $run->id)->orderBy('started_at')->get();
        $this->assertSame(['lessons', 'quizzes'], $rows->pluck('stage')->all());
        $this->assertSame(['120.000', '49.500'], $rows->pluck('seconds')->all());
        $this->assertSame(['exact', 'exact'], $rows->pluck('quality')->all());
    }

    public function testRunsWithoutEventsGetOneCoarseRowFromTheRunRow(): void
    {
        $run = $this->run_($this->bareSession(), 'outline', 'finished', $this->t('08:00:00'), $this->t('08:00:31'));

        $this->artisan('authoring-metrics:backfill')->assertSuccessful();

        $row = StageTiming::query()->where('run_id', $run->id)->sole();
        $this->assertSame(['outline', 'coarse', '31.000'], [$row->stage, $row->quality, $row->seconds]);
    }

    public function testAnOutlineRunWithOnlyItsRunEventsIsExact(): void
    {
        $run = $this->run_($this->bareSession(), 'outline', 'finished', $this->t('08:00:00'), $this->t('08:00:24'));
        $this->logged($run, 'RUN_STARTED', $this->t('08:00:00.100'));
        $this->logged($run, 'RUN_FINISHED', $this->t('08:00:24.100'));

        $this->artisan('authoring-metrics:backfill')->assertSuccessful();

        $row = StageTiming::query()->where('run_id', $run->id)->sole();
        $this->assertSame(['outline', 'exact', '24.000'], [$row->stage, $row->quality, $row->seconds]);
    }

    public function testRunsStillRunningAndRunsWithoutTimesAreLeftAlone(): void
    {
        $this->run_($this->bareSession(), 'generate', 'running', $this->t('08:00:00'), null);
        $this->run_($this->bareSession(), 'ingest', 'finished', null, null);

        $this->artisan('authoring-metrics:backfill')->assertSuccessful();

        $this->assertSame(0, StageTiming::query()->count());
    }

    public function testADryRunCountsButWritesNothing(): void
    {
        $this->runWithEvents();
        $this->run_($this->bareSession(), 'outline', 'finished', $this->t('08:00:00'), $this->t('08:00:31'));

        $this->artisan('authoring-metrics:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('Would record timings for 1 runs from events and 1 runs from the run row alone')
            ->assertSuccessful();

        $this->assertSame(0, StageTiming::query()->count());
    }

    public function testRunningItAgainWritesNothingNew(): void
    {
        $this->runWithEvents();
        $this->run_($this->bareSession(), 'outline', 'finished', $this->t('08:00:00'), $this->t('08:00:31'));
        $this->artisan('authoring-metrics:backfill')->assertSuccessful();
        $first = StageTiming::query()->orderBy('id')->get(['run_id', 'stage', 'seconds'])->toArray();

        $this->artisan('authoring-metrics:backfill')->expectsOutputToContain('Rows written: 0.')->assertSuccessful();

        $this->assertSame($first, StageTiming::query()->orderBy('id')->get(['run_id', 'stage', 'seconds'])->toArray());
    }

    public function testRunsRecordedLiveAreNotRecordedAgain(): void
    {
        $session = $this->toApplied($this->author());
        $before = StageTiming::query()->where('session_id', $session->id)->count();

        $this->artisan('authoring-metrics:backfill', ['--session' => $session->id])->assertSuccessful();

        $this->assertSame($before, StageTiming::query()->where('session_id', $session->id)->count());
    }

    public function testACrashedRunWithoutItsEndEventIsClosedByTheRunRow(): void
    {
        $session = $this->bareSession();
        $run = $this->run_($session, 'generate', 'failed', $this->t('09:00:00'), $this->t('09:01:30'));
        $this->logged($run, 'STEP_STARTED', $this->t('09:00:00'), ['stepName' => 'lessons']);

        $this->artisan('authoring-metrics:backfill')->assertSuccessful();

        $row = StageTiming::query()->where('run_id', $run->id)->sole();
        $this->assertSame(['lessons', 'incomplete', '90.000'], [$row->stage, $row->quality, $row->seconds]);
    }

    public function testOnlyTheNamedSessionIsBackfilled(): void
    {
        $a = $this->run_($this->bareSession(), 'outline', 'finished', $this->t('08:00:00'), $this->t('08:00:10'));
        $this->run_($this->bareSession(), 'outline', 'finished', $this->t('08:00:00'), $this->t('08:00:10'));

        $this->artisan('authoring-metrics:backfill', ['--session' => $a->session_id])->assertSuccessful();

        $this->assertSame([$a->id], StageTiming::query()->pluck('run_id')->all());
    }
}
