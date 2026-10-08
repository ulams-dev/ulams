<?php

namespace EscolaLms\Notifications\Tests\Mocks;

use EscolaLms\Core\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private User $user;
    private User $friend;
    private string $string;

    public function __construct(User $user, User $friend, string $string)
    {
        $this->user = $user;
        $this->friend = $friend;
        $this->string = $string;
    }
}
