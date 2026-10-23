<?php

namespace Ulams\AuthoringMetrics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Services\ReviewTime;

/**
 * Nightly: totals the anonymous review minutes per course and day, deletes raw minutes older than the retention (7
 * days) and refreshes the snapshots of the courses whose totals changed. Runs for the current tenant (`--domain=<host>`).
 */
class RollupCommand extends Command
{
    protected $signature = 'authoring-metrics:rollup';

    protected $description = 'Roll up anonymous review minutes into daily totals and delete the raw minutes after 7 days';

    public function handle(ReviewTime $time, CourseMetrics $metrics): int
    {
        if (!Schema::hasTable('authoring_activity_minutes') || !Schema::hasTable('authoring_course_daily')) {
            $this->warn('This database has no review time tables; nothing to do.');

            return self::SUCCESS;
        }
        $result = $time->rollup();
        foreach ($result['courses'] as $courseId) {
            $metrics->recompute($courseId);
        }
        $this->info("Rolled up {$result['days']} course days, deleted {$result['deleted']} raw minutes.");

        return self::SUCCESS;
    }
}
