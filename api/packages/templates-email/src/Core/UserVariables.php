<?php

namespace EscolaLms\TemplatesEmail\Core;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use Illuminate\Support\Facades\Lang;

class UserVariables extends EmailVariables
{
    const VAR_USER_NAME = '@VarUserName';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME => $faker->name(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME => $event->getUser()->name,
        ]);
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_USER_NAME,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            [
                self::VAR_USER_NAME,
            ];
        }
        return [];
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('User variables'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                . '<p>'
                . Lang::get('Custom mail to :user', ['user' => self::VAR_USER_NAME])
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
