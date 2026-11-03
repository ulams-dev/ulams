<?php

namespace Ulams\CourseVariants\Tests\Support;

use Ulams\CourseVariants\Contracts\FitSignals;

final class FakeFit implements FitSignals
{
    public int $calls = 0;

    public function __construct(public ?string $direction)
    {
    }

    public function assess(int $userId, int $courseId): ?array
    {
        $this->calls++;

        return $this->direction === null ? null : ['direction' => $this->direction, 'reasons' => [['rule' => 'fake', 'messageKey' => 'variant.too_easy', 'params' => ['lessons' => 2]]]];
    }
}
