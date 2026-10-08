<?php

namespace EscolaLms\CoursesImportExport\Services\Contracts;

use EscolaLms\Courses\Models\Course;

interface CloneCourseServiceContract
{
    public function clone(Course $course): void;
}
