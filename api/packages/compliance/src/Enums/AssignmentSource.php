<?php

namespace Ulams\Compliance\Enums;

final class AssignmentSource
{
    public const MANUAL = 'manual';
    public const RULE = 'rule';
    public const RECERTIFICATION = 'recertification';

    public const ALL = [self::MANUAL, self::RULE, self::RECERTIFICATION];
}
