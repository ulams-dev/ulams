<?php

namespace Ulams\Compliance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Core\Models\User;

/** A learner was enrolled in a learning path (manually or by a rule). The recipient is the learner. */
class PathEnrolled
{
    use Dispatchable, SerializesModels;

    public function __construct(private User $user, public PathEnrollment $enrollment)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
