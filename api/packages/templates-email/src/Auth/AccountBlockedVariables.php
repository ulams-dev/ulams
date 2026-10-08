<?php

namespace EscolaLms\TemplatesEmail\Auth;

use Illuminate\Support\Facades\Lang;

class AccountBlockedVariables extends UserVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('Account Blocked Notification'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                . '<p>'
                . Lang::get('Your account has been blocked.')
                . '</p>'
                . '</mj-text>'
                . '<mj-text>'
                . '<p>'
                . Lang::get('Please contact us')
                . '</p>'
                . '</mj-text>'
            )
        ];
    }
}
