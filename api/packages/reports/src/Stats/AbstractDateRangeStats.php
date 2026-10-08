<?php

namespace EscolaLms\Reports\Stats;

use Carbon\Carbon;
use EscolaLms\Reports\ValueObject\DateRange;

abstract class AbstractDateRangeStats implements StatsContract
{
    protected ?DateRange $dateRange;

    public function __construct(?DateRange $dateRange)
    {
        $this->dateRange = $dateRange ?? new DateRange();
    }

    public static function make(?Carbon $dateFrom = null, ?Carbon $dateTo = null): self
    {
        return new static(new DateRange($dateFrom, $dateTo));
    }
}
