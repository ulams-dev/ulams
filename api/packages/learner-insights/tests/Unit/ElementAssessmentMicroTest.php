<?php

declare(strict_types=1);

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LearnerInsights\Scoring\ElementAssessment;
use Ulams\LearnerInsights\Scoring\Reason;

final class ElementAssessmentMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testElementAssessmentConstructionWithDefaultLowWeight(): void
    {
        $reason = new Reason('rule1', 'messageKey1');
        $elementAssessment = new ElementAssessment('elementKey1', 'status1', [$reason]);

        self::assertEquals('elementKey1', $elementAssessment->elementKey);
        self::assertEquals('status1', $elementAssessment->status);
        self::assertEquals([$reason], $elementAssessment->reasons);
        self::assertFalse($elementAssessment->lowWeight);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testElementAssessmentConstructionWithLowWeightTrue(): void
    {
        $reason = new Reason('rule2', 'messageKey2');
        $elementAssessment = new ElementAssessment('elementKey2', 'status2', [$reason], true);

        self::assertEquals('elementKey2', $elementAssessment->elementKey);
        self::assertEquals('status2', $elementAssessment->status);
        self::assertEquals([$reason], $elementAssessment->reasons);
        self::assertTrue($elementAssessment->lowWeight);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testElementAssessmentConstructionWithMultipleReasons(): void
    {
        $reason1 = new Reason('rule3', 'messageKey3');
        $reason2 = new Reason('rule4', 'messageKey4');
        $elementAssessment = new ElementAssessment('elementKey3', 'status3', [$reason1, $reason2]);

        self::assertEquals('elementKey3', $elementAssessment->elementKey);
        self::assertEquals('status3', $elementAssessment->status);
        self::assertEquals([$reason1, $reason2], $elementAssessment->reasons);
        self::assertFalse($elementAssessment->lowWeight);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testElementAssessmentConstructionWithNoReasons(): void
    {
        $elementAssessment = new ElementAssessment('elementKey4', 'status4', []);

        self::assertEquals('elementKey4', $elementAssessment->elementKey);
        self::assertEquals('status4', $elementAssessment->status);
        self::assertEquals([], $elementAssessment->reasons);
        self::assertFalse($elementAssessment->lowWeight);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testElementAssessmentConstructionWithElementKeyOnly(): void
    {
        $elementAssessment = new ElementAssessment('elementKey5', 'status5', []);

        self::assertEquals('elementKey5', $elementAssessment->elementKey);
        self::assertEquals('status5', $elementAssessment->status);
        self::assertEquals([], $elementAssessment->reasons);
        self::assertFalse($elementAssessment->lowWeight);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testElementAssessmentConstructionWithLowWeightFalse(): void
    {
        $reason = new Reason('rule6', 'messageKey6');
        $elementAssessment = new ElementAssessment('elementKey6', 'status6', [$reason], false);

        self::assertEquals('elementKey6', $elementAssessment->elementKey);
        self::assertEquals('status6', $elementAssessment->status);
        self::assertEquals([$reason], $elementAssessment->reasons);
        self::assertFalse($elementAssessment->lowWeight);
    }
}
