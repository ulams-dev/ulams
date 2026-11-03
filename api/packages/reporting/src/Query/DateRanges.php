<?php

namespace Ulams\Reporting\Query;

use Carbon\CarbonImmutable;

/**
 * Presets of a report's date range. A saved report stores the preset, not dates, so "last 90 days" is evaluated on every
 * run. Bounds are in the application's time zone, the one the dataset timestamps are stored in.
 */
final class DateRanges
{
    public const PRESETS = ['last_7_days', 'last_30_days', 'last_90_days', 'last_12_months', 'this_month', 'last_month', 'this_quarter', 'this_year', 'last_year'];

    public static function known(string $preset): bool
    {
        return in_array($preset, self::PRESETS, true);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} inclusive start and end */
    public static function resolve(string $preset, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();

        return match ($preset) {
            'last_7_days' => [$today->subDays(6), $now->endOfDay()],
            'last_30_days' => [$today->subDays(29), $now->endOfDay()],
            'last_90_days' => [$today->subDays(89), $now->endOfDay()],
            'last_12_months' => [$today->subMonthsNoOverflow(12)->addDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            'last_year' => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
        };
    }
}
