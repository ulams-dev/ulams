<?php

namespace EscolaLms\Payments\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Support\Collection $payments
 */
interface Billable
{
    public function payments(): HasMany;
}
