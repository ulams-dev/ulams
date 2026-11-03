<?php

namespace Ulams\LearnerInsights\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Deletes learner signals older than the retention window (`learner_insights.retention.signals_days`), in chunks. */
class PruneCommand extends Command
{
    protected $signature = 'learner-insights:prune {--chunk=5000}';

    protected $description = 'Delete learner signals older than the retention window';

    public function handle(): int
    {
        $days = (int) config('learner_insights.retention.signals_days');
        if ($days <= 0) {
            $this->warn('Retention is not set; nothing pruned.');

            return self::SUCCESS;
        }
        $cutoff = now()->subDays($days);
        $chunk = max(1, (int) $this->option('chunk'));
        $total = 0;
        do {
            $ids = DB::table('learner_signals')->where('occurred_at', '<', $cutoff)->orderBy('id')->limit($chunk)->pluck('id');
            $total += $ids->isEmpty() ? 0 : DB::table('learner_signals')->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        $this->info("Pruned $total signals older than $days days.");

        // histories: transitions, and statuses of pairs that are no longer an active enrolment
        $history = (int) config('learner_insights.retention.history_days');
        if ($history > 0) {
            $limit = now()->subDays($history);
            $transitions = DB::table('learner_status_transitions')->where('occurred_at', '<', $limit)->delete();
            $active = DB::table('course_user')->where(fn ($q) => $q->whereNull('finished')->orWhere('finished', false))->select('user_id', 'course_id');
            $lapsed = DB::table('learner_statuses')->where('evaluated_at', '<', $limit)
                ->whereNotExists(fn ($q) => $q->fromSub($active, 'a')->whereColumn('a.user_id', 'learner_statuses.user_id')->whereColumn('a.course_id', 'learner_statuses.course_id'))
                ->delete();
            $this->info("Pruned $transitions transitions and $lapsed lapsed statuses older than $history days.");
        }

        return self::SUCCESS;
    }
}
