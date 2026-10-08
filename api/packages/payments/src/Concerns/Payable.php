<?php

namespace EscolaLms\Payments\Concerns;

use EscolaLms\Payments\Enums\Currency;
use EscolaLms\Payments\Facades\Payments;
use EscolaLms\Payments\Models\Payment;
use EscolaLms\Payments\Entities\PaymentProcessor;
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
