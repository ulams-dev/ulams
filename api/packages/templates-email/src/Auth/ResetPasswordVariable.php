<?php

namespace EscolaLms\TemplatesEmail\Auth;

use EscolaLms\Templates\Events\EventWrapper;

class ResetPasswordVariable extends CommonAuthVariables
{
    // TODO
    static function getActionLink(EventWrapper $event): string
    {
        return '';
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => '',
            'content' => ''
        ];
    }
}
