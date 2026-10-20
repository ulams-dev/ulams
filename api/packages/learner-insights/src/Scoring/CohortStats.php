<?php

namespace Ulams\LearnerInsights\Scoring;

/** Per element: the median seconds learners needed and how many learners that median rests on. */
final class CohortStats
{
    /** @param array<string,array{median_seconds:float,learners:int}> $elements */
    public function __construct(private readonly array $elements = [])
    {
    }

    public function medianSeconds(string $elementKey, int $minCohort): ?float
    {
        $e = $this->elements[$elementKey] ?? null;

        return $e && $e['learners'] >= $minCohort && $e['median_seconds'] > 0 ? (float) $e['median_seconds'] : null;
    }
}
