<?php

namespace Ulams\Lrs\Tests\Models;

use Ulams\Courses\Models\User as CoursesUser;
use Ulams\Courses\Tests\Database\Factories\UserFactory;

class User extends CoursesUser
{
    /**
     * Builds this class, not the parent's test model: Passport 13 resolves a token's user provider
     * from the model class (`auth.providers.users.model` is this class in the lrs suite).
     */
    public static function newFactory()
    {
        return new class extends UserFactory {
            protected $model = User::class;
        };
    }
}
