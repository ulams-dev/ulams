<?php

declare(strict_types=1);

namespace Ulams\CourseVariants\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseVariants\Support\NoTopicCorrespondence;

final class NoTopicCorrespondenceMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testPairsReturnsEmptyArrayForSameCourseIds()
    {
        $noTopicCorrespondence = new NoTopicCorrespondence();
        $result = $noTopicCorrespondence->pairs(1, 1);
        $this->assertEmpty($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPairsReturnsEmptyArrayForDifferentCourseIds()
    {
        $noTopicCorrespondence = new NoTopicCorrespondence();
        $result = $noTopicCorrespondence->pairs(1, 2);
        $this->assertEmpty($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPairsReturnsEmptyArrayForZeroCourseIds()
    {
        $noTopicCorrespondence = new NoTopicCorrespondence();
        $result = $noTopicCorrespondence->pairs(0, 0);
        $this->assertEmpty($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPairsReturnsEmptyArrayForNegativeCourseIds()
    {
        $noTopicCorrespondence = new NoTopicCorrespondence();
        $result = $noTopicCorrespondence->pairs(-1, -2);
        $this->assertEmpty($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPairsReturnsEmptyArrayForMixedCourseIds()
    {
        $noTopicCorrespondence = new NoTopicCorrespondence();
        $result = $noTopicCorrespondence->pairs(1, -1);
        $this->assertEmpty($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPairsReturnsEmptyArrayForLargeCourseIds()
    {
        $noTopicCorrespondence = new NoTopicCorrespondence();
        $result = $noTopicCorrespondence->pairs(1000, 2000);
        $this->assertEmpty($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPairsReturnsEmptyArrayForRandomCourseIds()
    {
        $noTopicCorrespondence = new NoTopicCorrespondence();
        $result = $noTopicCorrespondence->pairs(rand(1, 100), rand(101, 200));
        $this->assertEmpty($result);
    }
}
