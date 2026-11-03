<?php

namespace Ulams\Reporting\Enums;

use Ulams\Core\Enums\BasicEnum;

class ReportingPermissionsEnum extends BasicEnum
{
    /** List the datasets one may build reports on and run the reports shared with one. Admin and tutor. */
    const READ = 'reporting_read';
    /** Build and save reports. Admin. */
    const CREATE = 'reporting_create';
    /** Edit and delete reports of other people. Admin. */
    const MANAGE = 'reporting_manage';
    /** The AI usage dataset: calls, tokens and cost per day, task and model. Admin only. */
    const AI_USAGE = 'reporting_ai_usage';
}
