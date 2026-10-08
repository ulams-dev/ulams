<?php

namespace Ulams\Reports\Imports\Stats\Course\Sheets;

use Ulams\Courses\Models\CourseProgress;

class FinishedTopicsSecondsSheet extends FinishedTopicsSheet
{
    protected function prepareUpdateData($value, CourseProgress $courseProgress = null): array
    {
        return [
            'seconds' => $value,
        ];
    }
}
