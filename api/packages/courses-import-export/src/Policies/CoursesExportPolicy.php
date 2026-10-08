<?php

namespace Ulams\CoursesImportExport\Policies;

use Ulams\Core\Models\User;
use Ulams\CoursesImportExport\Enums\CoursesImportExportPermissionsEnum;
use Ulams\CoursesImportExport\Models\Course;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class CoursesExportPolicy
{
    use HandlesAuthorization;

    public function export(User $user, Course $course)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->can(CoursesImportExportPermissionsEnum::COURSES_EXPORT)) {
            return true;
        }
        if ($user->can(CoursesImportExportPermissionsEnum::COURSES_EXPORT_OWNED) && $course->author_id === $user->id) {
            return true;
        }
        if ($user->can(CoursesImportExportPermissionsEnum::COURSES_EXPORT_OWNED) && $course->author_id !== $user->id) {
            return Response::deny('You do not own this course.');
        }

        return false;
    }

    public function import(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->can(CoursesImportExportPermissionsEnum::COURSES_IMPORT)) {
            return true;
        }

        return false;
    }

    public function clone(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->can(CoursesImportExportPermissionsEnum::COURSES_CLONE)) {
            return true;
        }

        return false;
    }
}
