<?php

namespace EscolaLms\Templates\Tests\Mock;

use EscolaLms\Core\Models\User;

class TestEventWithToArray
{
    private User $user;
    private User $friend;

    public function __construct(User $user, User $friend)
    {
        $this->user = $user;
        $this->friend = $friend;
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'friend' => $this->friend,
        ];
    }
}
