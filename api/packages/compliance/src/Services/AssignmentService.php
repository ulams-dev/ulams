<?php

namespace Ulams\Compliance\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Enums\AssignmentSource;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Events\TrainingAssigned;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Services\Contracts\ProgressResetter;
use Ulams\Core\Models\User;
use Ulams\CourseAccess\Models\Course as AccessCourse;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Creates and cancels required-training obligations. Access to the course is granted through the
 * course-access service and the due date is written to the course deadline, so every existing
 * deadline reminder and the learner's own course list stay in step.
 */
class AssignmentService
{
    public function __construct(
        private CourseSettings $settings,
        private CourseAccessServiceContract $access,
        private ProgressResetter $resetter,
    ) {
    }

    /**
     * Idempotent: an open assignment for the same user and course is returned (its due date is
     * only ever brought forward). A learner who already holds a valid certificate for the course
     * satisfies the obligation at once, unless this is a recertification.
     */
    public function assign(
        User $user,
        Course $course,
        ?CarbonInterface $dueAt = null,
        string $source = AssignmentSource::MANUAL,
        ?User $by = null,
        ?FabricPDF $recertifies = null,
    ): Assignment {
        $dueAt ??= ($days = $this->settings->for($course->getKey())->default_due_days) ? now()->addDays($days) : null;
        $created = false;

        $assignment = DB::transaction(function () use ($user, $course, $dueAt, $source, $by, $recertifies, &$created) {
            $open = Assignment::query()->open()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->lockForUpdate()->first();
            if ($open) {
                if ($dueAt && (!$open->due_at || $dueAt->lt($open->due_at))) {
                    $open->forceFill(['due_at' => $dueAt])->save();
                    $this->writeDeadline($user, $course, $dueAt);
                }

                return $open;
            }

            $valid = $source === AssignmentSource::RECERTIFICATION ? null : $this->validCertificate($user, $course);
            $assignment = Assignment::query()->create([
                'user_id' => $user->getKey(),
                'course_id' => $course->getKey(),
                'source' => $source,
                'status' => $valid ? AssignmentStatus::COMPLETED : AssignmentStatus::ASSIGNED,
                'assigned_at' => now(),
                'assigned_by' => $by?->getKey(),
                'due_at' => $dueAt,
                'completed_at' => $valid ? now() : null,
                'certificate_id' => $valid?->getKey(),
                'recertifies_certificate_id' => $recertifies?->getKey(),
            ]);

            if (!$valid) {
                $this->access->addAccessForUsers(AccessCourse::findOrFail($course->getKey()), [$user->getKey()]);
                $this->startOver($user, $course);
                $this->writeDeadline($user, $course, $dueAt);
                $created = true;
            }

            return $assignment;
        });

        if ($created) {
            event(new TrainingAssigned($user, $course, $assignment));
        }

        return $assignment;
    }

    public function cancel(Assignment $assignment): Assignment
    {
        if ($assignment->isOpen()) {
            $assignment->forceFill(['status' => AssignmentStatus::CANCELLED])->save();
        }

        return $assignment;
    }

    public function validCertificate(User $user, Course $course): ?FabricPDF
    {
        return FabricPDF::query()
            ->where('user_id', $user->getKey())
            ->where('assignable_type', Course::class)
            ->where('assignable_id', $course->getKey())
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    private function startOver(User $user, Course $course): void
    {
        $finished = CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->where('finished', true)->exists();
        if ($finished) {
            $this->resetter->reset($user, $course);
        }
    }

    private function writeDeadline(User $user, Course $course, ?CarbonInterface $dueAt): void
    {
        CourseUserPivot::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->update(['deadline' => $dueAt]);
    }
}
