<?php

namespace EscolaLms\TemplatesEmail\AssignWithoutAccount;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\TemplatesEmail\Core\EmailVariables;
use Illuminate\Support\Facades\Lang;

class AssignToProductableVariables extends EmailVariables
{
    const VAR_PRODUCTABLE_NAME = "@VarProductableName";

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables($user), [
            self::VAR_PRODUCTABLE_NAME => $faker->word,
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_PRODUCTABLE_NAME => $event->getProductable()->getName(),
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => '',
            'content' => ''
        ];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_PRODUCTABLE_NAME
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_PRODUCTABLE_NAME,
            ];
        }

        return [];
    }
}
