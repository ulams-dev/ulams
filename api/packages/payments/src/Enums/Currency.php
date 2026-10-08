<?php

namespace EscolaLms\Payments\Enums;

use EscolaLms\Core\Enums\BasicEnum;
use Money\Currency as MoneyCurrency;

class Currency extends BasicEnum
{
    public const PLN = 'PLN';
    public const USD = 'USD';
    public const EUR = 'EUR';
    public const GBP = 'GBP';

    public function toMoneyCurrency(): MoneyCurrency
    {
        return new MoneyCurrency($this->value);
    }
}
