<?php

namespace EscolaLms\Notifications\Tests\Mocks;

use EscolaLms\Core\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DifferentTestEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private User $user;
    private string $string;

    public function __construct(User $user, string $string)
    {
        $this->user = $user;
        $this->string = $string;
    }
}
