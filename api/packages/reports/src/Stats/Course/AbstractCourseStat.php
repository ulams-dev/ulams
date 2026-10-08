<?php

namespace Ulams\Reports\Stats\Course;

use Ulams\Courses\Models\Course;
use Ulams\Reports\Stats\StatsContract;

abstract class AbstractCourseStat implements StatsContract
{
    protected Course $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public static function make(Course $course)
    {
        return new static($course);
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(Course::class);
    }
}
