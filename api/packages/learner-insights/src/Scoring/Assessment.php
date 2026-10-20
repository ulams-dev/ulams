<?php

namespace Ulams\LearnerInsights\Scoring;

final class Assessment
{
    /**
     * @param array<string,ElementAssessment> $elements only elements that are not on track, by element key
     * @param Reason[] $reasons course-level reasons
     */
    public function __construct(
        public readonly string $course,
        public readonly array $elements,
        public readonly array $reasons,
    ) {
    }
}
