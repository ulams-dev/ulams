<?php

namespace Ulams\CourseVariants\Support;

use Ulams\CourseVariants\Contracts\FitSignals;

final class NoFitSignals implements FitSignals
{
    public function assess(int $userId, int $courseId): ?array
    {
        return null;
    }
}
