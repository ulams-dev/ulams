<?php

namespace EscolaLms\TemplatesEmail\Auth;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\TemplatesEmail\Core\EmailVariables;

abstract class UserVariables extends EmailVariables
{
    const VAR_USER_EMAIL      = "@VarUserEmail";
    const VAR_USER_FIRST_NAME = "@VarStudentFirstName";
    const VAR_USER_LAST_NAME  = "@VarStudentLastName";
    const VAR_USER_FULL_NAME  = "@VarStudentFullName";

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables($user), [
            self::VAR_USER_EMAIL => $faker->email,
            self::VAR_USER_FIRST_NAME => $faker->firstName,
            self::VAR_USER_LAST_NAME => $faker->lastName,
            self::VAR_USER_FULL_NAME => $faker->name,
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $user = $event->getUser();
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_EMAIL => $user->email,
            self::VAR_USER_FIRST_NAME => $user->firstName,
            self::VAR_USER_LAST_NAME => $user->lastName,
            self::VAR_USER_FULL_NAME => $user->name,
        ]);
    }

    public static function requiredVariables(): array
    {
        return [];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        return [];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }
}
