<?php

namespace EscolaLms\Reports\Imports\Stats\Course\Sheets;

use EscolaLms\Courses\Models\CourseProgress;

class FinishedTopicsStatusesSheet extends FinishedTopicsSheet
{
    protected function prepareUpdateData($value, CourseProgress $courseProgress = null): array
    {
        $value = (int) $value;

        return [
            'status' => $value,
            'started_at' => $value > 0 ? ($this->course->active_from ?? now()->subDay()) : null,
            'finished_at' => $value === 2 ? ($this->course->active_to ?? now()->subMinutes(random_int(1, 120))) : null,
        ];
    }
}
