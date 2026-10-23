<?php

namespace Ulams\PolicyLifecycle\Events;

use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * A new policy version was approved and rolled out. The recipient is the policy owner; `departments` counts, per
 * department, the certificates revoked and the people reassigned.
 */
class PolicyChangeApproved extends CourseEvent
{
    /** @param array<int,array{department:string,revoked:int,reassigned:int}> $departments */
    public function __construct(User $user, Course $course, public Policy $policy, public PolicyVersion $version, public array $departments)
    {
        parent::__construct($user, $course);
    }
}
