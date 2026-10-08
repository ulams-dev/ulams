<?php

namespace Ulams\Reports\Stats\User;

use Ulams\Core\Models\User;
use Ulams\Reports\Stats\AbstractDateRangeStats;
use Ulams\Reports\ValueObject\DateRange;

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
