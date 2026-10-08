<?php

namespace Ulams\TemplatesEmail\Auth;

use Ulams\Templates\Events\EventWrapper;

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
