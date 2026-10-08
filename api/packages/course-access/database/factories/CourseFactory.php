<?php

namespace EscolaLms\CourseAccess\Database\Factories;

use EscolaLms\CourseAccess\Models\Course;
use EscolaLms\Courses\Database\Factories\CourseFactory as BaseCourseFactory;

class CourseFactory extends BaseCourseFactory
{
    protected $model = Course::class;
}
