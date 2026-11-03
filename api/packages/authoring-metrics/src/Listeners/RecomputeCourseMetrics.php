<?php

namespace Ulams\AuthoringMetrics\Listeners;

use Illuminate\Support\Facades\Log;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\CourseBuilder\Events\BuilderStageFinished;
use Ulams\CourseBuilder\Models\Session;

/**
 * Keeps the snapshot current: after a run that changes the course ends, when a course is published and when a Living
 * Course proposal is applied. A failure is logged and never reaches the build, the publish or the proposal.
 */
class RecomputeCourseMetrics
{
    private const RUNS = ['apply', 'sync', 'patch'];

    public function __construct(private readonly CourseMetrics $metrics)
    {
    }

    public function runEnded(BuilderStageFinished $event): void
    {
        if ($event->stage !== null || $event->outcome !== 'finished' || !in_array($event->runKind, self::RUNS, true)) {
            return;
        }
        $courseId = Session::query()->whereKey($event->sessionId)->value('course_id');
        $this->recompute($courseId === null ? null : (int) $courseId);
    }

    /** `CoursedPublished` (it extends the course events: `->course`, or the id) and `ProposalApplied` (`->proposal->session_id`). */
    public function courseOf(object $event): void
    {
        if (isset($event->proposal)) {
            $courseId = Session::query()->whereKey($event->proposal->session_id)->value('course_id');
        } else {
            $courseId = $event->getCourse()->getKey();
        }
        $this->recompute($courseId === null ? null : (int) $courseId);
    }

    private function recompute(?int $courseId): void
    {
        if ($courseId === null) {
            return;
        }
        try {
            $this->metrics->recompute($courseId);
        } catch (\Throwable $e) {
            Log::warning('authoring-metrics: snapshot not recomputed', ['course_id' => $courseId, 'error' => $e->getMessage()]);
        }
    }
}
