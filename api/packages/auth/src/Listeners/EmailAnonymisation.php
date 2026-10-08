<?php

namespace Ulams\Auth\Listeners;

use Ulams\Auth\Events\AccountDeleted;
use Ulams\Auth\Services\Contracts\UserServiceContract;

class EmailAnonymisation
{
    private UserServiceContract $userService;

    public function __construct(UserServiceContract $userService)
    {
        $this->userService = $userService;
    }

    public function handle(AccountDeleted $event): void
    {
        $this->userService->anonymiseEmail($event->getUser());
    }
}
