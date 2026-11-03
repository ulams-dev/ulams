<?php

declare(strict_types=1);

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LearnerInsights\Scoring\CohortStats;

final class CohortStatsMicroTest extends TestCase
{
    #[Test]
    public function testMedianSecondsKnownElementAboveMinimumCohort(): void
    {
        $stats = new CohortStats([
            'element1' => ['median_seconds' => 60.0, 'learners' => 10],
        ]);

        $this->assertEquals(60.0, $stats->medianSeconds('element1', 5));
    }

    #[Test]
    public function testMedianSecondsKnownElementBelowMinimumCohort(): void
    {
        $stats = new CohortStats([
            'element1' => ['median_seconds' => 60.0, 'learners' => 4],
        ]);

        $this->assertNull($stats->medianSeconds('element1', 5));
    }

    #[Test]
    public function testMedianSecondsUnknownElement(): void
    {
        $stats = new CohortStats([
            'element1' => ['median_seconds' => 60.0, 'learners' => 10],
        ]);

        $this->assertNull($stats->medianSeconds('element2', 5));
    }

    #[Test]
    public function testMedianSecondsZeroMedian(): void
    {
        $stats = new CohortStats([
            'element1' => ['median_seconds' => 0.0, 'learners' => 10],
        ]);

        $this->assertNull($stats->medianSeconds('element1', 5));
    }

    #[Test]
    public function testMedianSecondsEmptyDefault(): void
    {
        $stats = new CohortStats();

        $this->assertNull($stats->medianSeconds('element1', 5));
    }
}
