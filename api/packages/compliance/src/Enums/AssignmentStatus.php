<?php

namespace Ulams\Compliance\Enums;

final class AssignmentStatus
{
    public const ASSIGNED = 'assigned';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public const ALL = [self::ASSIGNED, self::COMPLETED, self::CANCELLED];
}
