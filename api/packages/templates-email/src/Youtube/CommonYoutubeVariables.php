<?php

namespace EscolaLms\TemplatesEmail\Youtube;

use Carbon\Carbon;
use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\TemplatesEmail\Core\EmailVariables;
use EscolaLms\Webinar\Models\Webinar;
use EscolaLms\Youtube\Facades\Youtube;

abstract class CommonYoutubeVariables extends EmailVariables
{
    const VAR_USER_EMAIL       = '@VarUserEmail';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_EMAIL       => $faker->email(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_EMAIL    => $event->getUser()->email
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
