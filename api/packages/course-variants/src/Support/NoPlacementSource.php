<?php

namespace Ulams\CourseVariants\Support;

use Ulams\CourseVariants\Contracts\PlacementSource;

final class NoPlacementSource implements PlacementSource
{
    public function questions(array $courses): array
    {
        return [];
    }
}
