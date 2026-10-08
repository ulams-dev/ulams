<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseUserPivot;
use EscolaLms\Reports\Stats\AbstractDateRangeStats;
use Illuminate\Support\Collection;

class Started extends AbstractDateRangeStats
{
    public function calculate(): Collection
    {
        return CourseUserPivot::query()
            ->selectRaw('DATE(created_at) AS date, COUNT(user_id) AS count')
            ->whereDate('created_at', '>=', $this->dateRange->getDateFrom())
            ->whereDate('created_at', '<=', $this->dateRange->getDateTo())
            ->groupBy('date')
            ->where('finished', '=', false)
            ->get(['date', 'count'])
            ->mapWithKeys(fn(CourseUserPivot $model) => [$model->date => $model->count]);
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(Course::class);
    }
}
