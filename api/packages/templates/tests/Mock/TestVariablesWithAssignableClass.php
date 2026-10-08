<?php

namespace Ulams\Templates\Tests\Mock;

use Ulams\Core\Models\User;

class TestVariablesWithAssignableClass extends TestVariables
{
    public static function assignableClass(): ?string
    {
        return User::class;
    }
}
