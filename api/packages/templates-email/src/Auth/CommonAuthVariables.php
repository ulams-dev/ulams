<?php

namespace Ulams\TemplatesEmail\Auth;

use Ulams\Core\Models\User;
use Ulams\Templates\Events\EventWrapper;

abstract class CommonAuthVariables extends UserVariables
{
    const VAR_ACTION_LINK     = "@VarActionLink";

    public static function mockedVariables(?User $user = null): array
    {
        return array_merge(parent::mockedVariables($user), [
            self::VAR_ACTION_LINK => url('/'),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_ACTION_LINK => static::getActionLink($event),
        ]);
    }

    abstract static function getActionLink(EventWrapper $event): string;

    public static function requiredVariables(): array
    {
        return [
            self::VAR_ACTION_LINK,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            return [
                self::VAR_ACTION_LINK
            ];
        }
        return [];
    }

    public static function assignableClass(): ?string
    {
        return null;
    }
}
