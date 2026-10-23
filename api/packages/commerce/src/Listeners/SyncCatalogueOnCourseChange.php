<?php

namespace Ulams\Commerce\Listeners;

use Illuminate\Support\Facades\Log;
use Ulams\Commerce\Jobs\SyncSellableJob;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Courses\Events\CourseStatusChanged;

/**
 * Publishing or unpublishing a course enables or disables every product that depends on it: the course, the bundles
 * that contain it and the passes on either. Only sellables that have a price are touched, so a tenant that does not
 * sell anything pays nothing for this listener.
 */
class SyncCatalogueOnCourseChange
{
    public function __construct(private readonly CatalogueSync $sync)
    {
    }

    public function handle(CourseStatusChanged $event): void
    {
        try {
            foreach ($this->sync->affectedByCourse((int) $event->getCourse()->getKey()) as $ref) {
                SyncSellableJob::dispatch($ref->type, $ref->id)->afterCommit();
            }
        } catch (\Throwable $e) {
            // never block publishing a course on commerce; the nightly check finds what was missed
            Log::error('Catalogue sync after a course status change failed', ['course' => $event->getCourse()->getKey(), 'error' => $e->getMessage()]);
            report($e);
        }
    }
}
