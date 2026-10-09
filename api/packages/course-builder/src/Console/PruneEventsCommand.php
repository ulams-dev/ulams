<?php

namespace Ulams\CourseBuilder\Console;

use Illuminate\Console\Command;
use Ulams\CourseBuilder\Models\Event;

class PruneEventsCommand extends Command
{
    protected $signature = 'course-builder:prune-events';

    protected $description = 'Delete Course Builder AG-UI events older than the retention period';

    public function handle(): int
    {
        $days = (int) config('course_builder.events_retention_days', 30);
        $deleted = Event::query()->where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} events older than {$days} days.");

        return self::SUCCESS;
    }
}
