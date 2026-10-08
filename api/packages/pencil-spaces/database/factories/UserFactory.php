<?php

namespace Ulams\PencilSpaces\Database\Factories;

use Ulams\PencilSpaces\Models\User;
use Database\Factories\Ulams\Core\Models\UserFactory as CoreUserFactory;

class UserFactory extends CoreUserFactory
{
    protected $model = User::class;
}
