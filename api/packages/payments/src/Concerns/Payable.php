<?php

namespace Ulams\Payments\Concerns;

use Ulams\Payments\Enums\Currency;
use Ulams\Payments\Facades\Payments;
use Ulams\Payments\Models\Payment;
use Ulams\Payments\Entities\PaymentProcessor;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Payable
{
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function getPaymentAmount(): int
    {
        return 0;
    }

    public function getPaymentCurrency(): ?Currency
    {
        return null;
    }

    public function getPaymentDescription(): string
    {
        return '';
    }

    public function getPaymentOrderId(): ?string
    {
        return null;
    }

    public function process(): PaymentProcessor
    {
        return Payments::processPayable($this);
    }
}
