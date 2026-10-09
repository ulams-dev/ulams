<?php

namespace Ulams\ExamplePlugin\Services\Contracts;

use Ulams\Core\Models\User;

interface GreetingServiceContract
{
    /** The tenant's greeting, optionally addressed to someone. */
    public function greeting(?string $name = null): string;

    /** Dispatches GreetingSent for the user and returns the greeting that was sent. */
    public function send(User $user): string;
}
