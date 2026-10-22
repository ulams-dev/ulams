<?php

namespace Ulams\AuthoringMetrics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\AuthoringMetrics\Services\StageTimingRecorder;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Models\Run;

/**
 * Rebuilds stage timings of builds that ran before they were recorded live. Runs whose AG-UI events
 * still exist (the log keeps 30 days) are replayed through the same recorder as the live listener, so
 * their stages are exact. Older runs keep only their own `started_at` and `finished_at`: one `coarse`
 * row for the whole run. Safe to repeat: a stage already recorded is never written twice, and a run that
 * already has rows is skipped. Runs against the tenant database of the current domain
 * (`--domain=<host>`); `ulams:upgrade` runs it once for every tenant.
 */
class BackfillCommand extends Command
{
    protected $signature = 'authoring-metrics:backfill {--session= : Only this Course Builder session id} {--chunk=200} {--dry-run : Count what would be written, write nothing}';

    protected $description = 'Record stage timings of past Course Builder runs from the AG-UI events and the run rows (idempotent)';

    public function handle(StageTimingRecorder $recorder): int
    {
        if (!Schema::hasTable('authoring_stage_timings') || !Schema::hasTable('course_builder_runs')) {
            $this->warn('This database has no Course Builder or authoring metrics tables; nothing to do.');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $before = StageTiming::query()->count();
        $exact = $coarse = $skipped = 0;

        $run = function () use ($recorder, $chunk, &$exact, &$coarse, &$skipped) {
            $query = Run::query()->whereIn('status', ['finished', 'failed', 'cancelled'])->orderBy('id');
            if ($this->option('session')) {
                $query->where('session_id', (string) $this->option('session'));
            }
            $query->chunkById($chunk, function ($runs) use ($recorder, &$exact, &$coarse, &$skipped) {
                foreach ($runs as $r) {
                    if (StageTiming::query()->where('run_id', $r->id)->exists()) {
                        $skipped++;

                        continue;
                    }
                    $this->replay($recorder, $r) ? $exact++ : ($recorder->coarse($r) ? $coarse++ : $skipped++);
                }
            }, 'id');
        };
        // a dry run does the real work and rolls it back, so the counts are exactly what a real run writes
        if ($dry) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
        } else {
            $run();
        }
        $written = $dry ? $exact + $coarse : StageTiming::query()->count() - $before;
        $this->info(($dry ? 'Would record' : 'Recorded') . " timings for {$exact} runs from events and {$coarse} runs from the run row alone ({$skipped} skipped: already recorded or no usable times).");
        if (!$dry) {
            $this->line("Rows written: {$written}.");
        }

        return self::SUCCESS;
    }

    /** Replays the stage events of one run; false when it has none (they were pruned). */
    private function replay(StageTimingRecorder $recorder, Run $run): bool
    {
        $events = Event::query()->where('run_id', $run->id)->whereIn('type', ['STEP_STARTED', 'STEP_FINISHED', 'RUN_FINISHED', 'RUN_ERROR'])->orderBy('id')->get();
        if ($events->isEmpty()) {
            return false;
        }
        $ended = false;
        foreach ($events as $event) {
            $at = EventLog::at($event);
            match ($event->type) {
                'STEP_STARTED' => $recorder->stageStarted($run->session_id, $run->id, $run->kind, (string) $event->payload['stepName'], $at),
                'STEP_FINISHED' => $recorder->stageFinished($run->id, (string) $event->payload['stepName'], $at),
                default => $ended = $this->end($recorder, $run, $at, $event->type === 'RUN_FINISHED'),
            };
        }
        if (!$ended && $run->finished_at !== null) {
            // the end event was pruned or never written (a crash): the run row knows when it ended
            $this->end($recorder, $run, \Carbon\CarbonImmutable::instance($run->finished_at), $run->status === 'finished');
        }

        return StageTiming::query()->where('run_id', $run->id)->exists();
    }

    private function end(StageTimingRecorder $recorder, Run $run, \Carbon\CarbonImmutable $at, bool $finished): bool
    {
        $recorder->runEnded($run, $at, $finished);

        return true;
    }
}
