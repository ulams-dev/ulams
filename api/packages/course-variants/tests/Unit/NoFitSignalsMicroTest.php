<?php

declare(strict_types=1);

namespace Ulams\CourseVariants\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseVariants\Support\NoFitSignals;

final class NoFitSignalsMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testAssessReturnsNullForValidUserAndCourseIds()
    {
        $noFitSignals = new NoFitSignals();
        $result = $noFitSignals->assess(1, 101);
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAssessReturnsNullForDifferentUserAndCourseIds()
    {
        $noFitSignals = new NoFitSignals();
        $result = $noFitSignals->assess(2, 202);
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAssessReturnsNullForZeroUserAndCourseIds()
    {
        $noFitSignals = new NoFitSignals();
        $result = $noFitSignals->assess(0, 0);
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAssessReturnsNullForNegativeUserAndCourseIds()
    {
        $noFitSignals = new NoFitSignals();
        $result = $noFitSignals->assess(-1, -101);
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAssessReturnsNullForLargeUserAndCourseIds()
    {
        $noFitSignals = new NoFitSignals();
        $result = $noFitSignals->assess(999999, 999999);
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAssessReturnsNullForMixedUserAndCourseIds()
    {
        $noFitSignals = new NoFitSignals();
        $result = $noFitSignals->assess(123, 456);
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAssessReturnsNullForSameUserAndCourseIds()
    {
        $noFitSignals = new NoFitSignals();
        $result = $noFitSignals->assess(789, 789);
        $this->assertNull($result);
    }
}
