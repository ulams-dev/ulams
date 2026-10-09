<?php

namespace Ulams\ExamplePlugin\Services;

use Ulams\Core\Models\User;
use Ulams\ExamplePlugin\Events\GreetingSent;
use Ulams\ExamplePlugin\Services\Contracts\GreetingServiceContract;
use Ulams\ExamplePlugin\UlamsExamplePluginServiceProvider;

class GreetingService implements GreetingServiceContract
{
    public function greeting(?string $name = null): string
    {
        $greeting = (string) config(UlamsExamplePluginServiceProvider::CONFIG_KEY . '.greeting');

        return $name ? "{$greeting}, {$name}" : $greeting;
    }

    public function send(User $user): string
    {
        $greeting = $this->greeting($user->first_name);

        GreetingSent::dispatch($user, $greeting);

        return $greeting;
    }
}
