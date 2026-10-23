<?php

namespace Ulams\PolicyLifecycle\Events;

use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * A learner's certificate was superseded by an approved policy change and they were given an exam (minor change) or the
 * course (major change). The recipient is the learner; `summary` is the source diff in one factual sentence.
 */
class PolicyRetrainingAssigned extends CourseEvent
{
    public function __construct(User $user, Course $course, public Assignment $assignment, public Policy $policy, public PolicyVersion $version, public string $kind, public string $summary)
    {
        parent::__construct($user, $course);
    }
}
