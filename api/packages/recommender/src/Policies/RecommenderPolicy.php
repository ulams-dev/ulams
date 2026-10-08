<?php

namespace Ulams\Recommender\Policies;

use Ulams\Auth\Models\User;
use Ulams\Courses\Enum\CoursesPermissionsEnum;
use Ulams\Recommender\Models\Course;
use Ulams\Recommender\Models\Lesson;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecommenderPolicy
{
    use HandlesAuthorization;

    public function course(User $user, Course $course): bool
    {
        return $user->canAny([CoursesPermissionsEnum::COURSE_CREATE, CoursesPermissionsEnum::COURSE_UPDATE, CoursesPermissionsEnum::COURSE_UPDATE_OWNED]);
    }

    public function topic(User $user, Lesson $lesson): bool
    {
        return $user->canAny([CoursesPermissionsEnum::COURSE_CREATE, CoursesPermissionsEnum::COURSE_UPDATE, CoursesPermissionsEnum::COURSE_UPDATE_OWNED]);
    }
}
