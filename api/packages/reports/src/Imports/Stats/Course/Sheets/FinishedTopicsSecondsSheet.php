<?php

namespace EscolaLms\Reports\Imports\Stats\Course\Sheets;

use EscolaLms\Courses\Models\CourseProgress;

class FinishedTopicsSecondsSheet extends FinishedTopicsSheet
{
    protected function prepareUpdateData($value, CourseProgress $courseProgress = null): array
    {
        return [
            'seconds' => $value,
        ];
    }
}
