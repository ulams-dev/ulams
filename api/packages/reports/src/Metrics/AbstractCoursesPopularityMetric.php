<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Courses\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class AbstractCoursesPopularityMetric extends AbstractCoursesMetric
{
    public function calculate(?int $limit = null): Collection
    {
        $query = Course::dontCache()->withCount(['users'])
            ->orderBy('users_count', 'DESC')
            ->take($this->getLimit($limit));

        return $this
            ->additionalConditions($query)
            ->get(['id', 'title', 'users_count'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->title,
                'value' => $item->users_count
            ]);
    }

    protected function additionalConditions(Builder $query): Builder
    {
        return $query;
    }
}
