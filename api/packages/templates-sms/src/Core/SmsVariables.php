<?php

namespace Ulams\TemplatesSms\Core;

use Ulams\Core\Models\User;
use Ulams\Templates\Contracts\TemplateVariableContract;
use Ulams\Templates\Core\AbstractTemplateVariableClass;
use Ulams\Templates\Events\EventWrapper;

abstract class SmsVariables extends AbstractTemplateVariableClass implements TemplateVariableContract
{
    /**
     * @return array<string, mixed>
     */
    public static function mockedVariables(?User $user = null): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function variablesFromEvent(EventWrapper $event): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public static function requiredSections(): array
    {
        return [];
    }
}
