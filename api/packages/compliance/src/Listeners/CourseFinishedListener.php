<?php

namespace Ulams\Compliance\Listeners;

use Ulams\Compliance\Services\CompletionService;
use Ulams\Courses\Events\CourseFinished;

class CourseFinishedListener
{
    public function __construct(private CompletionService $completion)
    {
    }

    public function handle(CourseFinished $event): void
    {
        $this->completion->courseFinished($event->getUser(), $event->getCourse());
    }
}
