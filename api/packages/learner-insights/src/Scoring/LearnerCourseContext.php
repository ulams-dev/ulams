<?php

namespace Ulams\LearnerInsights\Scoring;

use Carbon\CarbonImmutable;

/** Everything a scorer may look at: this learner, this course, nothing else. */
final class LearnerCourseContext
{
    public function __construct(
        public readonly int $userId,
        public readonly int $courseId,
        public readonly CarbonImmutable $now,
        public readonly SignalWindow $signals,
        public readonly CourseStructure $course,
        public readonly CohortStats $cohort,
        public readonly Thresholds $thresholds,
    ) {
    }
}
