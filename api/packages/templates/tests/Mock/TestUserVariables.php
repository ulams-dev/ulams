<?php

namespace EscolaLms\Templates\Tests\Mock;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Contracts\TemplateVariableContract;
use EscolaLms\Templates\Core\AbstractTemplateVariableClass;
use EscolaLms\Templates\Events\EventWrapper;

class TestUserVariables extends AbstractTemplateVariableClass implements TemplateVariableContract
{
    const VAR_USER_EMAIL   = '@VarUserEmail';

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $user = $event->getUser();
        return [
            self::VAR_USER_EMAIL => $user->email,
        ];
    }

    public static function mockedVariables(?User $user = null): array
    {
        return [
            self::VAR_USER_EMAIL => $user ? $user->email : 'user.email@test.com',
        ];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }

    // Variables can have some of the channel sections that are optional marked as required;
    // This will be merged with channel required sections during validation
    public static function requiredSections(): array
    {
        return [];
    }

    public static function requiredVariables(): array
    {
        return [];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_USER_EMAIL,
            ];
        }
        return [];
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => 'New friend request',
            'content' => '<h1>Hello ' . self::VAR_USER_EMAIL . '!</h1>'
        ];
    }
}
