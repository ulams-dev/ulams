<?php

namespace EscolaLms\Reports\Stats\Cart;

use EscolaLms\Cart\Models\Order;
use EscolaLms\Core\Models\User;
use EscolaLms\Reports\Stats\StatsContract;

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
