<?php

namespace Ulams\Compliance\Mail;

class PathEnrolledVariables extends PathVariables
{
    public static function defaultSectionsContent(): array
    {
        return self::content('Learning path: :path', '<h1>Hello :user_name!</h1><p>You have been enrolled in the learning path ":path". Due date: :due_date.</p>');
    }
}
