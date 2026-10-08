<?php

namespace EscolaLms\Reports\Tests\Models;

use EscolaLms\Cart\Database\Factories\UserFactory;

class TestUserFactory extends UserFactory
{
    protected $model = TestUser::class;
}
