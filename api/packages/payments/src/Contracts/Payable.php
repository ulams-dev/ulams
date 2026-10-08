<?php

namespace Ulams\Payments\Contracts;

use Ulams\Core\Models\User;
use Ulams\Payments\Enums\Currency;
use Ulams\Payments\Entities\PaymentProcessor;
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
