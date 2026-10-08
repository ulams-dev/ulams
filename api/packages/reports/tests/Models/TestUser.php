<?php

namespace Ulams\Reports\Tests\Models;

use Ulams\Cart\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseAuthorPivot;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\Traits\HasCourses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestUser extends User
{
    use HasCourses;
    use HasFactory;

    protected static function newFactory(): TestUserFactory
    {
        return TestUserFactory::new();
    }

    public function courses(): BelongsToMany
    {
        /* @var $this \Ulams\Core\Models\User */
        return $this->belongsToMany(Course::class, 'course_user', 'user_id', 'course_id')->using(CourseUserPivot::class);
    }

    public function authoredCourses(): BelongsToMany
    {
        /* @var $this \Ulams\Core\Models\User */
        return $this->belongsToMany(Course::class, 'course_author', 'author_id', 'course_id')->using(CourseAuthorPivot::class);
    }
}
