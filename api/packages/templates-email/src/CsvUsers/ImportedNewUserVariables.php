<?php

namespace EscolaLms\TemplatesEmail\CsvUsers;

use EscolaLms\TemplatesEmail\Auth\ResetPasswordVariables;
use Illuminate\Support\Facades\Lang;

class ImportedNewUserVariables extends ResetPasswordVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('User Import Notification'),
            'content' => self::wrapWithMjml(
                '<mj-text>'
                . '<p>'
                . Lang::get('You are receiving this email because you have been added to the system.')
                . '</p>'
                . '</mj-text>'
                . '<mj-button href="' . self::VAR_ACTION_LINK . '">' . Lang::get('Set Password') . '</mj-button>'
                . '<mj-text>'
                . '<p>'
                . Lang::get('This link will expire in :count minutes.', ['count' => self::VAR_ACTION_LINK_EXPIRATION])
                . '</p>'
                . '</mj-text>'
            )
        ];
    }
}
