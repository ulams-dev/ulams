<?php

namespace Ulams\LearnerInsights\Scoring;

final class ElementAssessment
{
    /**
     * @param Reason[] $reasons
     * @param bool $lowWeight true when only the low-weight `repetition` rule fired
     */
    public function __construct(
        public readonly string $elementKey,
        public readonly string $status,
        public readonly array $reasons,
        public readonly bool $lowWeight = false,
    ) {
    }
}
