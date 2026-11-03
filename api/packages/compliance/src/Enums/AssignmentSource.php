<?php

namespace Ulams\Compliance\Enums;

final class AssignmentSource
{
    public const MANUAL = 'manual';
    public const RULE = 'rule';
    public const RECERTIFICATION = 'recertification';
    /** An approved policy change reassigned the course or its exam (policy-lifecycle, ADR 0140). */
    public const POLICY_CHANGE = 'policy_change';

    public const ALL = [self::MANUAL, self::RULE, self::RECERTIFICATION, self::POLICY_CHANGE];
}
