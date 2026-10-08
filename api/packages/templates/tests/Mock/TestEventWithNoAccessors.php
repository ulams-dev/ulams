<?php

namespace Ulams\Templates\Tests\Mock;

use Ulams\Core\Models\User;

class TestEventWithNoAccessors
{
    private User $user;
    private User $friend;

    public function __construct(User $user, User $friend)
    {
        $this->user = $user;
        $this->friend = $friend;
    }
}
