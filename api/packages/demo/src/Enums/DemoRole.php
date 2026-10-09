<?php

namespace Ulams\Demo\Enums;

/**
 * Roles a visitor can be logged in as by `POST /api/demo/login`. The values are the
 * `Ulams\Core\Enums\UserRole` role names.
 */
enum DemoRole: string
{
    case STUDENT = 'student';
    case ADMIN = 'admin';
    case TUTOR = 'tutor';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
