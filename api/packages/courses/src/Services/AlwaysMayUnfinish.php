<?php

namespace Ulams\Courses\Services;

use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Services\Contracts\CourseCompletionGuardContract;

/** The behaviour before Living Course: adding a topic clears `finished` on the next progress update. */
class AlwaysMayUnfinish implements CourseCompletionGuardContract
{
    public function mayUnfinish(Course $course, User $user): bool
    {
        return true;
    }
}
