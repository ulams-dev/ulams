<?php

namespace Ulams\Compliance\Events;

use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;

/**
 * A required course is due within the configured reminder window. The recipient is the learner.
 */
class TrainingDueSoon extends CourseEvent
{
    public function __construct(User $user, Course $course, public Assignment $assignment, public int $daysLeft)
    {
        parent::__construct($user, $course);
    }
}
