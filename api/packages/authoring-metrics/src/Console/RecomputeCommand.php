<?php

namespace Ulams\AuthoringMetrics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\CourseBuilder\Models\Session;

/**
 * Rebuilds the authoring metric snapshots of the current tenant (`--domain=<host>`). The snapshot is derived data:
 * running this any number of times gives the same figures for the same recorded history.
 */
class RecomputeCommand extends Command
{
    protected $signature = 'authoring-metrics:recompute {--course= : Only this course id} {--active : Only courses whose builder session changed in the last 2 days (the nightly run)}';

    protected $description = 'Recompute the authoring metric snapshot of courses built with the Course Builder';

    public function handle(CourseMetrics $metrics): int
    {
        if (!Schema::hasTable('authoring_course_metrics') || !Schema::hasTable('course_builder_sessions')) {
            $this->warn('This database has no Course Builder or authoring metrics tables; nothing to do.');

            return self::SUCCESS;
        }
        $query = Session::query()->whereNotNull('course_id')->orderBy('course_id');
        if ($this->option('course')) {
            $query->where('course_id', (int) $this->option('course'));
        }
        if ($this->option('active')) {
            $query->where('updated_at', '>=', now()->subDays(2));
        }
        $done = 0;
        foreach ($query->pluck('course_id')->unique() as $courseId) {
            $metrics->recompute((int) $courseId);
            $done++;
        }
        $this->info("Recomputed {$done} course snapshots.");

        return self::SUCCESS;
    }
}
