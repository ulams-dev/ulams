<?php

namespace EscolaLms\CourseAccess\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\CourseAccess\Enum\CourseAccessPermissionEnum;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourseAccessEnquiryPolicy
{
    use HandlesAuthorization;

    public function createOwn(User $user): bool
    {
        return $user->can(CourseAccessPermissionEnum::CREATE_OWN_COURSE_ACCESS_ENQUIRY);
    }

    public function deleteOwn(User $user, CourseAccessEnquiry $courseAccessEnquiry): bool
    {
        return $user->can(CourseAccessPermissionEnum::DELETE_OWN_COURSE_ACCESS_ENQUIRY)
            && $courseAccessEnquiry->user_id === $user->getKey();
    }

    public function listOwn(User $user): bool
    {
        return $user->can(CourseAccessPermissionEnum::LIST_OWN_COURSE_ACCESS_ENQUIRY);
    }

    public function list(User $user): bool
    {
        return $user->can(CourseAccessPermissionEnum::LIST_COURSE_ACCESS_ENQUIRY);
    }

    public function delete(User $user): bool
    {
        return $user->can(CourseAccessPermissionEnum::DELETE_COURSE_ACCESS_ENQUIRY);
    }

    public function approve(User $user): bool
    {
        return $user->can(CourseAccessPermissionEnum::APPROVE_COURSE_ACCESS_ENQUIRY);
    }
}
