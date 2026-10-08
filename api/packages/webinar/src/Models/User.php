<?php

namespace Ulams\Webinar\Models;

use Ulams\Auth\Models\User as AuthUser;
use Ulams\Webinar\Models\Traits\HasWebinars;
use Ulams\Webinar\Tests\Database\Factories\UserFactory;

class User extends AuthUser
{
    use HasWebinars;

    public static function newFactory()
    {
        // @phpstan-ignore-next-line
        return UserFactory::new();
    }
}
