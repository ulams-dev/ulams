<?php

namespace EscolaLms\Reports\Tests\Models;

use EscolaLms\Cart\Models\User;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseAuthorPivot;
use EscolaLms\Courses\Models\CourseUserPivot;
use EscolaLms\Courses\Models\Traits\HasCourses;
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
        /* @var $this \EscolaLms\Core\Models\User */
        return $this->belongsToMany(Course::class, 'course_user', 'user_id', 'course_id')->using(CourseUserPivot::class);
    }

    public function authoredCourses(): BelongsToMany
    {
        /* @var $this \EscolaLms\Core\Models\User */
        return $this->belongsToMany(Course::class, 'course_author', 'author_id', 'course_id')->using(CourseAuthorPivot::class);
    }
}
