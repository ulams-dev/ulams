<?php

namespace Ulams\Reports\Tests\Models;

use Ulams\Cart\Database\Factories\UserFactory;

class TestUserFactory extends UserFactory
{
    protected $model = TestUser::class;
}
