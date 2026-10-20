<?php

namespace Ulams\Compliance\Services;

use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Closes assignments when the learner finishes the course and ties the issued certificate to them.
 * The two events (course finished, certificate created) arrive in either order.
 */
class CompletionService
{
    public function courseFinished(User $user, Course $course): void
    {
        // CourseFinished is also fired when access is removed: only a really finished course counts.
        $finished = CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->where('finished', true)->exists();
        if (!$finished) {
            return;
        }

        Assignment::query()->open()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->get()
            ->each(function (Assignment $assignment) {
                $certificate = FabricPDF::query()
                    ->where('user_id', $assignment->user_id)
                    ->where('assignable_type', Course::class)
                    ->where('assignable_id', $assignment->course_id)
                    ->where('created_at', '>=', $assignment->assigned_at)
                    ->latest('id')
                    ->first();
                $assignment->forceFill([
                    'status' => AssignmentStatus::COMPLETED,
                    'completed_at' => now(),
                    'certificate_id' => $certificate?->getKey(),
                ])->save();
            });
    }

    public function certificateIssued(FabricPDF $certificate): void
    {
        if ($certificate->assignable_type !== Course::class || !$certificate->assignable_id) {
            return;
        }

        // A newer certificate replaces the older ones of the same learner and course.
        FabricPDF::query()
            ->where('user_id', $certificate->user_id)
            ->where('assignable_type', Course::class)
            ->where('assignable_id', $certificate->assignable_id)
            ->where('id', '<', $certificate->getKey())
            ->whereNull('superseded_by_id')
            ->update(['superseded_by_id' => $certificate->getKey()]);

        $assignment = Assignment::query()
            ->where('user_id', $certificate->user_id)
            ->where('course_id', $certificate->assignable_id)
            ->whereNull('certificate_id')
            ->whereIn('status', [AssignmentStatus::ASSIGNED, AssignmentStatus::COMPLETED])
            ->where('assigned_at', '<=', $certificate->created_at)
            ->latest('id')
            ->first();
        $assignment?->forceFill(['certificate_id' => $certificate->getKey()])->save();
    }
}
