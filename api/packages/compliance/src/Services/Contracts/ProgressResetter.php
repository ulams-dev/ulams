<?php

namespace Ulams\Compliance\Services\Contracts;

use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;

/**
 * Starts a course over for a learner who has to do it again (recertification, a new assignment after
 * the certificate lapsed). The default implementation clears the learner's topic progress; replace it
 * to archive progress elsewhere.
 */
interface ProgressResetter
{
    public function reset(User $user, Course $course): void;
}
