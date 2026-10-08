<?php

namespace Ulams\TemplatesPdf\Core;

use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateVariableContract;
use Ulams\Templates\Core\AbstractTemplateVariableClass;
use Ulams\Templates\Events\EventWrapper;

abstract class PdfVariables extends AbstractTemplateVariableClass implements TemplateVariableContract
{
    const VAR_APP_NAME       = '@VarAppName';
    const VAR_TODAY = '@VarToday';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return [
            self::VAR_APP_NAME => config('app.name'),
            self::VAR_TODAY => today()->format('d.m.Y'),
        ];
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return [
            self::VAR_APP_NAME => config('app.name'),
            self::VAR_TODAY => today()->format('d.m.Y'),
        ];
    }

    public static function requiredSections(): array
    {
        return [];
    }
}
