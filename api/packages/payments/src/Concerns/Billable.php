<?php

namespace EscolaLms\Payments\Concerns;

use EscolaLms\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Billable
{
    public function payments(): HasMany
    {
        /** @var \EscolaLms\Core\Models\User $this */
        return $this->hasMany(Payment::class);
    }
}
