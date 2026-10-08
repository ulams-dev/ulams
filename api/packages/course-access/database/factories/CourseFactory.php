<?php

namespace Ulams\CourseAccess\Database\Factories;

use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Database\Factories\CourseFactory as BaseCourseFactory;

class CourseFactory extends BaseCourseFactory
{
    protected $model = Course::class;
}
