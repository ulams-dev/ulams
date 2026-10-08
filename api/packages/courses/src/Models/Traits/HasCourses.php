<?php

namespace Ulams\Courses\Models\Traits;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasCourses
{
    public function courses(): BelongsToMany
    {
        /* @var $this \Ulams\Core\Models\User */
        return $this->belongsToMany(Course::class)->using(CourseUserPivot::class)->withTimestamps();
    }

    public function finishedCourse(int $id): bool
    {
        return $this->courses()->where('course_id', $id)->wherePivot('finished', true)->exists();
    }
}
