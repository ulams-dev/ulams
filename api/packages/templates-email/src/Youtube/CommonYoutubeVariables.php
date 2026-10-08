<?php

namespace Ulams\TemplatesEmail\Youtube;

use Carbon\Carbon;
use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;
use Ulams\TemplatesEmail\Core\EmailVariables;
use Ulams\Webinar\Models\Webinar;
use Ulams\Youtube\Facades\Youtube;

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
