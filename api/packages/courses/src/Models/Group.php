<?php

namespace Ulams\Courses\Models;

use Ulams\Auth\Models\Group as AuthGroup;
use Ulams\Courses\Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends AuthGroup
{
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)->using(CourseGroupPivot::class)->withTimestamps();
    }

    protected static function newFactory()
    {
        return new GroupFactory();
    }
}
