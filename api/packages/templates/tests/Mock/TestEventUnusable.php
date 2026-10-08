<?php

namespace Ulams\Templates\Tests\Mock;

use Ulams\Core\Models\User;

class TestEventUnusable
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
