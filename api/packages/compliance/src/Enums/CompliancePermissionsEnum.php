<?php

namespace Ulams\Compliance\Enums;

use Ulams\Core\Enums\BasicEnum;

class CompliancePermissionsEnum extends BasicEnum
{
    const MANAGE = 'compliance_manage';
    const REPORT = 'compliance_report';
    /** Tag the courses one authors with competencies (confirm or reject AI suggestions). */
    const COMPETENCIES = 'compliance_competencies';
}
