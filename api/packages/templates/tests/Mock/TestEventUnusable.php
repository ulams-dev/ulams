<?php

namespace EscolaLms\Templates\Tests\Mock;

use EscolaLms\Core\Models\User;

class TestEventUnusable
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
