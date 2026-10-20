<?php

namespace Ulams\Compliance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Core\Models\User;

/** A learner finished every course of a learning path. The recipient is the learner. */
class PathCompleted
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
