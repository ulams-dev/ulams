<?php

namespace Ulams\Compliance\Services;

use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\PathEnrollment;

/**
 * Where a required course sits in the learner's learning path, for the step e-mails: the path, "step n of m" and
 * the next step still to do. Null when the course is not a step of a path the learner is enrolled in.
 */
class PathContext
{
    public function __construct(private StepProgress $progress)
    {
    }

    /** @return array{enrollment_id: int, path_id: int, path_title: string, step: int, steps: int, next_title: string}|null */
    public function forAssignment(Assignment $assignment): ?array
    {
        $enrollments = PathEnrollment::query()
            ->where('user_id', $assignment->user_id)
            ->whereHas('path.steps', fn ($query) => $query->where('course_id', $assignment->course_id))
            ->with('path')
            ->get();
        // an open path wins over a finished one; the newest wins among equals
        $enrollment = $enrollments->sortBy(fn (PathEnrollment $e) => [$e->completed_at ? 1 : 0, -$e->getKey()])->first();
        if (!$enrollment || !$enrollment->path) {
            return null;
        }

        $steps = $enrollment->path->steps()->with('course')->get()->values();
        $index = $steps->search(fn ($step) => $step->course_id === $assignment->course_id);
        if ($index === false) {
            return null;
        }
        $user = $assignment->user;
        $next = $steps->slice($index + 1)->first(fn ($step) => !$user || !$this->progress->isSatisfied($user, $step->course_id));

        return [
            'enrollment_id' => $enrollment->getKey(),
            'path_id' => $enrollment->path_id,
            'path_title' => (string) $enrollment->path->title,
            'step' => $index + 1,
            'steps' => $steps->count(),
            'next_title' => (string) ($next?->course?->title ?? ''),
        ];
    }
}
