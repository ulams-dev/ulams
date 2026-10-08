<?php

namespace EscolaLms\Payments\Contracts;

use EscolaLms\Core\Models\User;
use EscolaLms\Payments\Enums\Currency;
use EscolaLms\Payments\Entities\PaymentProcessor;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Payable
{
    public function payments(): MorphMany;

    public function getPaymentAmount(): int;
    public function getPaymentCurrency(): ?Currency;
    public function getPaymentDescription(): string;
    public function getPaymentOrderId(): ?string;
    public function getUser(): ?User;

    public function process(): PaymentProcessor;
}
