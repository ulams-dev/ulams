<?php

namespace Ulams\Reports\Stats\Cart;

use Ulams\Cart\Models\Order;
use Ulams\Core\Models\User;
use Ulams\Reports\Stats\StatsContract;

abstract class AbstractCartStat implements StatsContract
{
    public static function make(): self
    {
        return new static();
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(User::class) && class_exists(Order::class);
    }
}
