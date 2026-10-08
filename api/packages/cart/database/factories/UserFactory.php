<?php

namespace Ulams\Cart\Database\Factories;

use Database\Factories\Ulams\Core\Models\UserFactory as CoreUserFactory;
use Ulams\Cart\Models\User;

class UserFactory extends CoreUserFactory
{
    protected $model = User::class;
}
