<?php

namespace Ulams\ExamplePlugin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ulams\Core\Models\User;

/**
 * Dispatched when an admin sends a greeting to a user. The class lives in the `Ulams\`
 * namespace and carries a User, so the notifications package stores it as a database
 * notification of that user and the templates package can send it on any channel an
 * admin has a template for.
 */
class GreetingSent
{
    use Dispatchable, SerializesModels;

    // Public so SerializesModels can restore them when the event is queued.
    public User $user;

    public string $greeting;

    public function __construct(User $user, string $greeting)
    {
        $this->user = $user;
        $this->greeting = $greeting;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getGreeting(): string
    {
        return $this->greeting;
    }
}
