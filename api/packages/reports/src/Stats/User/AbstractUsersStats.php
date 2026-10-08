<?php

namespace EscolaLms\Reports\Stats\User;

use EscolaLms\Core\Models\User;
use EscolaLms\Reports\Stats\AbstractDateRangeStats;
use EscolaLms\Reports\ValueObject\DateRange;

abstract class AbstractUsersStats extends AbstractDateRangeStats
{
    public function __construct(?DateRange $dateRange = null)
    {
        parent::__construct($dateRange);
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(User::class);
    }
}
