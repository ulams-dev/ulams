<?php

namespace Ulams\Payments\Concerns;

use Ulams\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Billable
{
    public function payments(): HasMany
    {
        /** @var \Ulams\Core\Models\User $this */
        return $this->hasMany(Payment::class);
    }
}
