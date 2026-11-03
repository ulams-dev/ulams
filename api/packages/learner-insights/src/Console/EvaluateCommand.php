<?php

namespace Ulams\LearnerInsights\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ulams\LearnerInsights\Services\NudgeService;
use Ulams\LearnerInsights\Services\OutcomeTracker;
use Ulams\LearnerInsights\Services\StatusService;

/**
 * Nightly sweep for the time-based rules (inactivity, abandoned elements): every enrolled, unfinished
 * learner with activity in the last 60 days, plus everyone currently at risk or struggling.
 */
class EvaluateCommand extends Command
{
    protected $signature = 'learner-insights:evaluate {--course= : Only this course id}';

    protected $description = 'Re-evaluate learner statuses (time-based rules)';

    public function handle(StatusService $statuses, NudgeService $nudges, OutcomeTracker $outcomes): int
    {
        if (!config('learner_insights.enabled')) {
            $this->warn('Learner insights is disabled for this tenant; nothing evaluated.');

            return self::SUCCESS;
        }
        $since = now()->subDays(60);
        $active = DB::table('learner_signals')->whereNotNull('course_id')->where('occurred_at', '>=', $since)->select('user_id', 'course_id');
        $flagged = DB::table('learner_statuses')->where('element_key', '')->where('status', '!=', 'on_track')->select('user_id', 'course_id');
        $pairs = DB::query()->fromSub($active->union($flagged), 'p')->distinct();
        if ($this->option('course')) {
            $pairs->where('course_id', (int) $this->option('course'));
        }

        $n = 0;
        foreach ($pairs->orderBy('user_id')->orderBy('course_id')->get(['user_id', 'course_id']) as $pair) {
            $statuses->evaluate((int) $pair->user_id, (int) $pair->course_id);
            $n++;
        }
        $this->info("Evaluated $n learner-course pairs.");

        // learners at risk who were never considered for a nudge, then the interventions whose window ended
        $considered = $nudges->sweep();
        $closed = $outcomes->closeExpired();
        $this->info("Considered $considered at-risk learners for a nudge; closed $closed expired interventions.");

        return self::SUCCESS;
    }
}
