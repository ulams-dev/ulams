<?php

namespace EscolaLms\Reports\ValueObject;

use Carbon\Carbon;

class DateRange implements ValueObject
{
    private ?Carbon $dateFrom;

    private ?Carbon $dateTo;

    public function __construct(?Carbon $dateFrom = null, ?Carbon $dateTo = null)
    {
        $this->dateFrom = $dateFrom ?? Carbon::now()->startOfDay();
        $this->dateTo = $dateTo ?? Carbon::now()->endOfDay();
    }

    public function getDateFrom(): ?Carbon
    {
        return $this->dateFrom;
    }

    public function getDateTo(): ?Carbon
    {
        return $this->dateTo;
    }
}
