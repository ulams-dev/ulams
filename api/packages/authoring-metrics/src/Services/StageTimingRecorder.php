<?php

namespace Ulams\AuthoringMetrics\Services;

use Carbon\CarbonImmutable;
use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Run;

/**
 * Turns the start and end of Course Builder stages into `authoring_stage_timings` rows. The live
 * listener and the backfill both go through this class, so a stage is recorded the same way, and only
 * once, whichever path sees it first (unique on run, stage and start).
 *
 * Wall time: parallel lessons overlap and the author waits for the stage, so a stage is the time from
 * its first start to its finish, never the sum of its model calls.
 */
class StageTimingRecorder
{
    public function stageStarted(string $sessionId, string $runId, string $runKind, string $stage, CarbonImmutable $at): void
    {
        StageTiming::query()->firstOrCreate(
            ['run_id' => $runId, 'stage' => $stage, 'started_at' => $at],
            ['session_id' => $sessionId, 'run_kind' => $runKind, 'quality' => StageTiming::INCOMPLETE],
        );
    }

    /** Closes the stage that is still open (a retried stage opens a new row). A finish without a start is ignored. */
    public function stageFinished(string $runId, string $stage, CarbonImmutable $at): void
    {
        $row = StageTiming::query()->where('run_id', $runId)->where('stage', $stage)->whereNull('finished_at')->orderByDesc('started_at')->first();
        if ($row === null) {
            return;
        }
        $this->close($row, $at, StageTiming::EXACT);
    }

    /**
     * The run ended. Stages left open by a failed or cancelled run are closed at the end of the run and
     * flagged `incomplete`. A run that reported no stages (the outline run) becomes one row named after
     * its kind, from RUN_STARTED to now; `incomplete` when the run did not finish.
     */
    public function runEnded(Run $run, CarbonImmutable $at, bool $finished): void
    {
        foreach (StageTiming::query()->where('run_id', $run->id)->whereNull('finished_at')->get() as $open) {
            $this->close($open, $at, StageTiming::INCOMPLETE);
        }
        if (StageTiming::query()->where('run_id', $run->id)->exists()) {
            return;
        }
        $start = $this->runStart($run);
        if ($start === null) {
            return;
        }
        $row = StageTiming::query()->create([
            'session_id' => $run->session_id, 'run_id' => $run->id, 'run_kind' => $run->kind, 'stage' => $run->kind,
            'started_at' => $start, 'quality' => $finished ? StageTiming::EXACT : StageTiming::INCOMPLETE,
        ]);
        $this->close($row, $at, $finished ? StageTiming::EXACT : StageTiming::INCOMPLETE);
    }

    /** One row for the whole run, from the run row alone: what remains once the AG-UI events are pruned. */
    public function coarse(Run $run): bool
    {
        if ($run->started_at === null || $run->finished_at === null || StageTiming::query()->where('run_id', $run->id)->exists()) {
            return false;
        }
        $row = StageTiming::query()->create([
            'session_id' => $run->session_id, 'run_id' => $run->id, 'run_kind' => $run->kind, 'stage' => $run->kind,
            'started_at' => CarbonImmutable::instance($run->started_at), 'quality' => StageTiming::COARSE,
        ]);
        $this->close($row, CarbonImmutable::instance($run->finished_at), StageTiming::COARSE);

        return true;
    }

    private function close(StageTiming $row, CarbonImmutable $at, string $quality): void
    {
        $ms = max(0, (int) round(($at->getTimestampMs() - $row->started_at->getTimestampMs())));
        $row->forceFill(['finished_at' => $at, 'seconds' => number_format($ms / 1000, 3, '.', ''), 'quality' => $quality])->save();
    }

    /** RUN_STARTED to the millisecond while the event exists, else the run row's own start. */
    private function runStart(Run $run): ?CarbonImmutable
    {
        $event = Event::query()->where('run_id', $run->id)->where('type', 'RUN_STARTED')->orderBy('id')->first();
        if ($event !== null) {
            return EventLog::at($event);
        }

        return $run->started_at === null ? null : CarbonImmutable::instance($run->started_at);
    }
}
