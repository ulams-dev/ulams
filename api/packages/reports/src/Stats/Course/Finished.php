<?php

namespace Ulams\Reports\Stats\Course;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Reports\Stats\AbstractDateRangeStats;
use Illuminate\Support\Collection;

class Finished extends AbstractDateRangeStats
{
    public function calculate(): Collection
    {
        return CourseUserPivot::query()
            ->selectRaw('DATE(updated_at) AS date, COUNT(user_id) AS count')
            ->whereDate('updated_at', '>=', $this->dateRange->getDateFrom())
            ->whereDate('updated_at', '<=', $this->dateRange->getDateTo())
            ->groupBy('date')
            ->where('finished', '=', true)
            ->get(['date', 'count'])
            ->mapWithKeys(fn(CourseUserPivot $model) => [$model->date => $model->count]);
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(Course::class);
    }
}
