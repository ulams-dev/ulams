<?php

namespace Ulams\CoursesImportExport\Services\Contracts;

use Ulams\Courses\Models\Course;

interface CloneCourseServiceContract
{
    public function clone(Course $course): void;
}
