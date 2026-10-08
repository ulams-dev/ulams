<?php

namespace EscolaLms\TemplatesEmail\Auth;

use EscolaLms\Core\Models\User;
use EscolaLms\TemplatesEmail\Core\EmailVariables;
use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Facades\Lang;

class VerifyUserAccountVariables extends EmailVariables
{
    const VAR_REGISTERED_USER_NAME = '@VarRegisteredUserName';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();

        return array_merge(parent::mockedVariables($user), [
            self::VAR_REGISTERED_USER_NAME => $faker->name,
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_REGISTERED_USER_NAME => $event->getRegisteredUser()->name,
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_REGISTERED_USER_NAME,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            [
                self::VAR_REGISTERED_USER_NAME,
            ];
        }
        return [];
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('Verify User account'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                    . '<p>'
                    . Lang::get('The new user has registered. Please activate :name account', ['name' => self::VAR_REGISTERED_USER_NAME])
                    . '</p>'
                    . '</mj-text>'
                    . '<mj-text>'
                    . '<p>'
                    . Lang::get('User account must be enabled by admin')
                    . '</p>'
                    . '</mj-text>'
            )
        ];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }
}
