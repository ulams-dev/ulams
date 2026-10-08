<?php

namespace Ulams\Categories\Repositories\Criteria;

use Ulams\Categories\Models\Category;
use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class CourseInCategory extends Criterion
{
    public function __construct(Category $category)
    {
        parent::__construct(null, $category);
    }

    public function apply(Builder $query): Builder
    {
        return $query->where('courses.category_id', $this->value->getKey());
    }
}
