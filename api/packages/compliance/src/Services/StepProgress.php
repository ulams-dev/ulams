<?php

namespace Ulams\Compliance\Services;

use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Has a learner already done a course? A valid certificate counts, an expired or revoked one does not;
 * a course that issues no certificate counts once it is finished or its assignment is completed.
 */
class StepProgress
{
    public function isSatisfied(User $user, int $courseId): bool
    {
        $certificates = FabricPDF::query()
            ->where('user_id', $user->getKey())
            ->where('assignable_type', Course::class)
            ->where('assignable_id', $courseId);

        if ((clone $certificates)->exists()) {
            return (clone $certificates)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        }

        return CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $courseId)->where('finished', true)->exists()
            || Assignment::query()->where('user_id', $user->getKey())->where('course_id', $courseId)->where('status', AssignmentStatus::COMPLETED)->exists();
    }
}
