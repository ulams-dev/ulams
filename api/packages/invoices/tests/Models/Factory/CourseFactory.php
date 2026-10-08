<?php

namespace EscolaLms\Invoices\Tests\Models\Factory;

use EscolaLms\Courses\Database\Factories\CourseFactory as BaseCourseFactory;
use EscolaLms\Invoices\Tests\Models\Course;

class CourseFactory extends BaseCourseFactory
{
    protected $model = Course::class;
}
