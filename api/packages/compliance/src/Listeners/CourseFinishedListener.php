<?php

namespace Ulams\Compliance\Listeners;

use Ulams\Compliance\Services\CompletionService;
use Ulams\Compliance\Services\PathEnrollmentService;
use Ulams\Courses\Events\CourseFinished;

class CourseFinishedListener
{
    public function __construct(private CompletionService $completion, private PathEnrollmentService $paths)
    {
    }

    public function handle(CourseFinished $event): void
    {
        $this->completion->courseFinished($event->getUser(), $event->getCourse());
        $this->paths->advance($event->getUser(), $event->getCourse());
    }
}
