<?php

namespace Ulams\Templates\Tests\Mock;

/**
 * Default contents that depend on the application language, like the `Lang::get` ones of the e-mail package.
 */
class TestVariablesLocalized extends TestVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => 'Title in ' . app()->getLocale(),
            'content' => 'Content in ' . app()->getLocale() . ' ' . self::VAR_USER_EMAIL . ' ' . self::VAR_FRIEND_EMAIL,
        ];
    }
}
