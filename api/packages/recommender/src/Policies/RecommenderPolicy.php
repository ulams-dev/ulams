<?php

namespace EscolaLms\Recommender\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\Enum\CoursesPermissionsEnum;
use EscolaLms\Recommender\Models\Course;
use EscolaLms\Recommender\Models\Lesson;
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
