<?php

namespace Ulams\Courses\Listeners;

use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Services\Contracts\DeadlineCalculatorServiceContract;

class SetNewDeadlineForReassignedUser
{
    public function __construct(private DeadlineCalculatorServiceContract $deadlineCalculatorServvice)
    {
    }

    public function handle(CourseAssigned $event): void
    {
        $user = $event->getUser();
        $course = $event->getCourse();

        $pivot = CourseUserPivot::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        $progressExists = CourseProgress::query()
            ->where('user_id', $user->getKey())
            ->whereIn('topic_id', $course->topics()->pluck('topics.id')->toArray())
            ->exists();

        if ($pivot && $progressExists) {
            $pivot->deadline = $this->deadlineCalculatorServvice->calculate($course);
            $pivot->save();
        }
    }
}
