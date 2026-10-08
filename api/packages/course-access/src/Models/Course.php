<?php

namespace EscolaLms\CourseAccess\Models;

use EscolaLms\CourseAccess\Database\Factories\CourseFactory;
use EscolaLms\Courses\Models\Course as BaseCourse;

class Course extends BaseCourse
{
    public static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }
}
