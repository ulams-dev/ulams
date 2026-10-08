<?php

namespace Ulams\Consultations\Tests\Models;

use Ulams\Consultations\Models\User as ConsultationUser;
use Ulams\Consultations\Tests\Database\Factories\UserFactory;

class User extends ConsultationUser
{
    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
