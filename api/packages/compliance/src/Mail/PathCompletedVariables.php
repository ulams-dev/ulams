<?php

namespace Ulams\Compliance\Mail;

class PathCompletedVariables extends PathVariables
{
    public static function defaultSectionsContent(): array
    {
        return self::content('Learning path completed: :path', '<h1>Congratulations :user_name!</h1><p>You have completed every course of the learning path ":path".</p>');
    }
}
