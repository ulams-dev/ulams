<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Compliance\Events\TrainingCompleted;
use Ulams\Compliance\Events\TrainingCompletedForManager;
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
    public function __construct(private NotificationLog $log, private CourseSettings $settings, private ManagerService $managers)
    {
    }

    public function courseFinished(User $user, Course $course): void
    {
        // CourseFinished is also fired when access is removed: only a really finished course counts.
        $finished = CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->where('finished', true)->exists();
        if (!$finished) {
            return;
        }

        Assignment::query()->open()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->get()
            ->each(function (Assignment $assignment) use ($user, $course) {
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
                $this->announce($assignment, $user, $course);
            });
    }

    /**
     * The completion notices: the learner always, the learner's manager when the course is mandatory. Each goes through
     * the notification log, so an assignment closes with at most one notice per recipient.
     */
    private function announce(Assignment $assignment, User $user, Course $course): void
    {
        $this->log->once('training_completed', 'assignment', $assignment->getKey(), 0, $user->getKey(), fn () => event(new TrainingCompleted($user, $course, $assignment)));

        if (!$this->settings->for($course->getKey())->mandatory) {
            return;
        }
        $manager = $this->managers->managerOf($user);
        if ($manager) {
            $this->log->once('training_completed_manager', 'assignment', $assignment->getKey(), 0, $manager->getKey(), fn () => event(new TrainingCompletedForManager($manager, $course, $assignment, $user)));
        }
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

        $this->recordCourseVersion($certificate);

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

    /**
     * The version a certificate attests: the Living Course version of the course when that package keeps
     * one, otherwise the course's last update (set by templates-pdf) stays.
     */
    private function recordCourseVersion(FabricPDF $certificate): void
    {
        if (!Schema::hasTable('living_course_audit')) {
            return;
        }
        $version = DB::table('living_course_audit')->where('course_id', $certificate->assignable_id)->max('version_to');
        if ($version) {
            $certificate->forceFill(['course_version' => 'v' . $version])->saveQuietly();
        }
    }
}
