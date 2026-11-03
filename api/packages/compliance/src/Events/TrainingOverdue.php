<?php

namespace Ulams\Compliance\Events;

use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;

/**
 * A required course passed its due date. The recipient is the learner.
 */
class TrainingOverdue extends CourseEvent
{
    public function __construct(User $user, Course $course, public Assignment $assignment)
    {
        parent::__construct($user, $course);
    }
}
