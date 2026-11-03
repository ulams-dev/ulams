<?php

namespace Ulams\AuthoringMetrics\Enums;

use Ulams\Core\Enums\BasicEnum;

class AuthoringMetricsPermissionsEnum extends BasicEnum
{
    /** Read the authoring metric of every course. The author of a course always reads their own. */
    const READ = 'authoring_metrics_read';
}
