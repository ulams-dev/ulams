<?php

namespace Ulams\Courses\Services\Contracts;

use Ulams\Courses\Models\Course;
use Carbon\Carbon;

interface DeadlineCalculatorServiceContract
{
    public function calculate(Course $course, ?Carbon $startDate = null): ?Carbon;
}
