<?php

namespace Ulams\Compliance\Services;

use Carbon\CarbonInterface;
use Ulams\Compliance\Enums\AssignmentSource;
use Ulams\Compliance\Events\PathCompleted;
use Ulams\Compliance\Events\PathEnrolled;
use Ulams\Compliance\Models\LearningPath;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Compliance\Models\PathStep;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;

/**
 * Enrols learners in learning paths. A step becomes a required-training assignment as soon as all its
 * prerequisites are done, so a learner only ever sees the courses they can start; finishing the last
 * step completes the path.
 */
class PathEnrollmentService
{
    public function __construct(private AssignmentService $assignments, private StepProgress $progress)
    {
    }

    /** Idempotent: a learner is enrolled in a path once. */
    public function enroll(User $user, LearningPath $path, ?CarbonInterface $dueAt = null, string $source = 'manual', ?int $ruleId = null): PathEnrollment
    {
        $existing = PathEnrollment::query()->where('user_id', $user->getKey())->where('path_id', $path->getKey())->first();
        if ($existing) {
            return $existing;
        }

        $enrollment = PathEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'path_id' => $path->getKey(),
            'source' => $source,
            'rule_id' => $ruleId,
            'enrolled_at' => now(),
            'due_at' => $dueAt,
        ]);
        event(new PathEnrolled($user, $enrollment));
        $this->advanceEnrollment($enrollment);

        return $enrollment;
    }

    /** A course was finished: unlock what it opens in every open path of the learner. */
    public function advance(User $user, Course $course): void
    {
        // CourseFinished is also fired when access is removed: only a really finished course unlocks anything.
        if (!CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->where('finished', true)->exists()) {
            return;
        }
        PathEnrollment::query()
            ->whereNull('completed_at')
            ->where('user_id', $user->getKey())
            ->whereHas('path.steps', fn ($query) => $query->where('course_id', $course->getKey()))
            ->each(fn (PathEnrollment $enrollment) => $this->advanceEnrollment($enrollment));
    }

    public function advanceEnrollment(PathEnrollment $enrollment): void
    {
        $user = $enrollment->user;
        $steps = $enrollment->path->steps()->get();
        if (!$user || $steps->isEmpty() || $enrollment->completed_at) {
            return;
        }

        $done = $steps->filter(fn (PathStep $step) => $this->progress->isSatisfied($user, $step->course_id))->pluck('course_id')->all();

        foreach ($steps as $step) {
            if (in_array($step->course_id, $done, true) || array_diff($step->prerequisiteIds(), $done) !== []) {
                continue;
            }
            $course = Course::find($step->course_id);
            if ($course) {
                $this->assignments->assign($user, $course, $enrollment->due_at, $enrollment->source === 'rule' ? AssignmentSource::RULE : AssignmentSource::MANUAL);
            }
        }

        if (count($done) === $steps->count()) {
            $enrollment->forceFill(['completed_at' => now()])->save();
            event(new PathCompleted($user, $enrollment));
        }
    }

    /**
     * The learner's view of a path: each step with `done`, `available` (prerequisites done, not yet done) or
     * `locked`, and the prerequisites it waits for.
     *
     * @return list<array{course_id: int, title: string|null, status: string, waiting_for: list<int>}>
     */
    public function stepStatuses(User $user, LearningPath $path): array
    {
        $steps = $path->steps()->with('course')->get();
        $done = $steps->filter(fn (PathStep $step) => $this->progress->isSatisfied($user, $step->course_id))->pluck('course_id')->all();

        return $steps->map(function (PathStep $step) use ($done) {
            $waiting = array_values(array_diff($step->prerequisiteIds(), $done));

            return [
                'course_id' => $step->course_id,
                'title' => $step->course?->title,
                'status' => in_array($step->course_id, $done, true) ? 'done' : ($waiting === [] ? 'available' : 'locked'),
                'waiting_for' => $waiting,
            ];
        })->all();
    }
}
