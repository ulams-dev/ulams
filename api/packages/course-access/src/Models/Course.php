<?php

namespace Ulams\CourseAccess\Models;

use Ulams\CourseAccess\Database\Factories\CourseFactory;
use Ulams\Courses\Models\Course as BaseCourse;

class Course extends BaseCourse
{
    public static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }
}
