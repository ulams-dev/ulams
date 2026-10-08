<?php

namespace Ulams\Invoices\Tests\Models\Factory;

use Ulams\Courses\Database\Factories\CourseFactory as BaseCourseFactory;
use Ulams\Invoices\Tests\Models\Course;

class CourseFactory extends BaseCourseFactory
{
    protected $model = Course::class;
}
