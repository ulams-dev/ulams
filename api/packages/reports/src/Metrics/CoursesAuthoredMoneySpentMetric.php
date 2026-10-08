<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Courses\Enum\CoursesPermissionsEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CoursesAuthoredMoneySpentMetric extends AbstractCoursesMoneySpentMetric
{
    protected function additionalConditions(Builder $query): Builder
    {
        $user = Auth::user();
        $usersTable = $user->getTable();
        return $query
            ->whereHas('authors', fn (Builder $q) => $q->where($usersTable . '.id', '=', $user->getKey()));
    }

    public static function requiredPermissions(): array
    {
        return [CoursesPermissionsEnum::COURSE_LIST_OWNED];
    }
}
