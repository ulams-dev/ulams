<?php

namespace Ulams\Reports\Stats;

interface StatsContract
{
    public function calculate();
    public static function requiredPackagesInstalled(): bool;
}
