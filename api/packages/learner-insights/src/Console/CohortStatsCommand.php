<?php

namespace Ulams\LearnerInsights\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly: per course element, the median seconds learners needed to finish it and how many learners
 * the median rests on (input of the `slow_element` rule). Aggregates only, no learner is identifiable.
 */
class CohortStatsCommand extends Command
{
    protected $signature = 'learner-insights:cohort-stats';

    protected $description = 'Compute the median time per course element from learner signals';

    public function handle(): int
    {
        $now = now();
        $written = 0;
        // learners who finished the element, with the seconds they spent on it
        $finished = DB::table('learner_signals')->where('type', 'topic.completed')->whereNotNull('course_id')->select('user_id', 'course_id', 'element_key')->distinct();
        $rows = DB::query()->fromSub($finished, 'f')
            ->join('learner_signals as s', function ($j) {
                $j->on('s.user_id', '=', 'f.user_id')->on('s.course_id', '=', 'f.course_id')->on('s.element_key', '=', 'f.element_key')->where('s.type', '=', 'topic.progressed');
            })
            ->groupBy('f.course_id', 'f.element_key', 'f.user_id')
            ->get(['f.course_id', 'f.element_key', 'f.user_id', DB::raw('SUM(s.value) as seconds')]);

        foreach ($rows->groupBy(fn ($r) => $r->course_id . '|' . $r->element_key) as $group) {
            $seconds = $group->pluck('seconds')->map(fn ($v) => (float) $v)->sort()->values();
            $n = $seconds->count();
            $median = $n % 2 ? $seconds[intdiv($n, 2)] : ($seconds[$n / 2 - 1] + $seconds[$n / 2]) / 2;
            $first = $group->first();
            DB::table('learner_insights_element_stats')->updateOrInsert(
                ['course_id' => $first->course_id, 'element_key' => $first->element_key],
                ['median_seconds' => $median, 'learners' => $n, 'computed_at' => $now],
            );
            $written++;
        }
        $this->info("Computed $written element medians.");

        return self::SUCCESS;
    }
}
