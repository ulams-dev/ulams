<?php

namespace Ulams\CoursesImportExport\Services;

use Ulams\Courses\Models\Course;
use Ulams\CoursesImportExport\Jobs\CloneCourse;
use Ulams\CoursesImportExport\Services\Contracts\CloneCourseServiceContract;
use Exception;

class CloneCourseService implements CloneCourseServiceContract
{
    /**
     * @throws Exception
     */
    public function clone(Course $course): void
    {
        CloneCourse::dispatch($course, auth()->user());
    }
}
