<?php

namespace EscolaLms\Templates\Tests\Mock;

use EscolaLms\Core\Models\User;

class TestVariablesWithAssignableClass extends TestVariables
{
    public static function assignableClass(): ?string
    {
        return User::class;
    }
}
