<?php

namespace EscolaLms\Templates\Tests\Mock;

class TestVariablesWithMissingDefaultContent extends TestVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => 'New friend request',
        ];
    }
}
