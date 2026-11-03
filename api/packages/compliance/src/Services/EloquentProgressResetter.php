<?php

namespace Ulams\Compliance\Services;

use Ulams\Compliance\Services\Contracts\ProgressResetter;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\CourseUserPivot;

class EloquentProgressResetter implements ProgressResetter
{
    public function reset(User $user, Course $course): void
    {
        $topicIds = $course->topics()->pluck('topics.id');

        CourseProgress::query()->where('user_id', $user->getKey())->whereIn('topic_id', $topicIds)->delete();
        CourseUserPivot::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->update(['finished' => false]);
    }
}
