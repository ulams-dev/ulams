<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\AuthoringMetrics\Services\StageTimingRecorder;
use Ulams\AuthoringMetrics\Tests\TestCase;

/**
 * Sanity check against a run we measured (case study of 13 passages, docs/plans/authoring-metric-and-report-builder.md):
 * outline 24 s, lessons, quizzes and checks 6 min 57 s, apply 23 s. The stage events are scripted with those
 * timestamps; nothing in the code knows the figures.
 */
class MeasuredRunFixtureTest extends TestCase
{
    public function testTheMeasuredRunComesBackAsMeasured(): void
    {
        $session = $this->bareSession();
        $recorder = app(StageTimingRecorder::class);

        $outline = $this->run_($session, 'outline', 'finished', $this->t('09:00:00'), $this->t('09:00:24'));
        $this->logged($outline, 'RUN_STARTED', $this->t('09:00:00'));
        $recorder->runEnded($outline, $this->t('09:00:24'), true);

        $generate = $this->run_($session, 'generate', 'finished', $this->t('09:05:00'), $this->t('09:11:57'));
        // lessons run in parallel windows; the stages follow one another: 3:10 + 0:40 + 1:00 + 2:07 = 6:57
        foreach ([['lessons', '09:05:00', '09:08:10'], ['grounding', '09:08:10', '09:08:50'], ['interactions', '09:08:50', '09:09:50'], ['quizzes', '09:09:50', '09:11:57']] as [$stage, $from, $to]) {
            $recorder->stageStarted($session->id, $generate->id, 'generate', $stage, $this->t($from));
            $recorder->stageFinished($generate->id, $stage, $this->t($to));
        }

        $apply = $this->run_($session, 'apply', 'finished', $this->t('09:20:00'), $this->t('09:20:23'));
        $recorder->stageStarted($session->id, $apply->id, 'apply', 'apply', $this->t('09:20:00'));
        $recorder->stageFinished($apply->id, 'apply', $this->t('09:20:23'));

        $seconds = fn (string $kind) => (float) StageTiming::query()->where('run_kind', $kind)->sum(DB::raw('seconds'));
        $this->assertEqualsWithDelta(24.0, $seconds('outline'), 0.001);
        $this->assertEqualsWithDelta(6 * 60 + 57, $seconds('generate'), 0.001);
        $this->assertEqualsWithDelta(23.0, $seconds('apply'), 0.001);
        $this->assertEqualsWithDelta(24 + 417 + 23, (float) StageTiming::query()->sum(DB::raw('seconds')), 0.001);
        $this->assertSame(['exact'], StageTiming::query()->pluck('quality')->unique()->values()->all());
    }
}
