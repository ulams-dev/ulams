<?php

namespace Ulams\LearnerInsights\Scoring;

use Carbon\CarbonInterface;

final class CourseStructure
{
    /**
     * @param array<string,string> $labels element key => label shown in reasons
     */
    public function __construct(
        public readonly bool $enrolled,
        public readonly bool $finished,
        public readonly ?CarbonInterface $enrolledAt,
        public readonly array $labels = [],
    ) {
    }

    public function label(string $elementKey): string
    {
        return $this->labels[$elementKey] ?? $elementKey;
    }
}
