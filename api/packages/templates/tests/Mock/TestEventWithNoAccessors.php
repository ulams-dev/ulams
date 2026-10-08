<?php

namespace EscolaLms\Templates\Tests\Mock;

use EscolaLms\Core\Models\User;

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
