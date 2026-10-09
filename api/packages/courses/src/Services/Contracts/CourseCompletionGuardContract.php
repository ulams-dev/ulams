<?php

namespace Ulams\Courses\Services\Contracts;

use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;

/**
 * Consulted before a learner who finished a course is marked as not finished again because the
 * course changed (a topic was added). The default allows it, which is the behaviour of the
 * upstream package; Living Course keeps `finished` for courses it updates (ADR 0033).
 */
interface CourseCompletionGuardContract
{
    public function mayUnfinish(Course $course, User $user): bool;
}
