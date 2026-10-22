<?php

namespace Ulams\Compliance\Events;

use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;

/**
 * A learner finished a required course (its assignment was closed). The recipient is the learner. Sent once per
 * assignment; a course that was already satisfied by a valid certificate when it was assigned never fires it.
 */
class TrainingCompleted extends CourseEvent
{
    public function __construct(User $user, Course $course, public Assignment $assignment)
    {
        parent::__construct($user, $course);
    }
}
