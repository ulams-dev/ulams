<?php

namespace Ulams\Compliance\Events;

use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;

/**
 * A learner is overdue: the recipient is the learner's manager, `learner` is who is overdue.
 */
class TrainingEscalated extends CourseEvent
{
    public function __construct(User $user, Course $course, public Assignment $assignment, public User $learner)
    {
        parent::__construct($user, $course);
    }
}
