<?php

namespace EscolaLms\Consultations\Tests\Models;

use EscolaLms\Consultations\Models\User as ConsultationUser;
use EscolaLms\Consultations\Tests\Database\Factories\UserFactory;

class User extends ConsultationUser
{
    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
