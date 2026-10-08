<?php

namespace EscolaLms\PencilSpaces\Database\Factories;

use EscolaLms\PencilSpaces\Models\User;
use Database\Factories\EscolaLms\Core\Models\UserFactory as CoreUserFactory;

class UserFactory extends CoreUserFactory
{
    protected $model = User::class;
}
