<?php

namespace EscolaLms\TemplatesEmail\Auth;

use Illuminate\Support\Facades\Lang;

class AccountDeletedVariables extends UserVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('Account Deleted Notification'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                . '<p>'
                . Lang::get('Your account has been deleted.')
                . '</p>'
                . '</mj-text>'
                . '<mj-text>'
                . '<p>'
                . Lang::get('If you have not requested to delete your account, please contact the administrator')
                . '</p>'
                . '</mj-text>'
            )
        ];
    }
}
