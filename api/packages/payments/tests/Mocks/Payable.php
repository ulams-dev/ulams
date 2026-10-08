<?php

namespace EscolaLms\Payments\Tests\Mocks;

use EscolaLms\Core\Models\User;
use EscolaLms\Payments\Concerns\Payable as ConcernsPayable;
use EscolaLms\Payments\Contracts\Payable as ContractsPayable;
use EscolaLms\Payments\Enums\Currency;
use Illuminate\Support\Collection;

class Payable implements ContractsPayable
{
    use ConcernsPayable;

    private ?User $user = null;
    private ?Currency $currency;
    private ?string $order_id;
    private Collection $payments;
    private int $amount;
    private string $description;

    public function __construct(int $amount, ?Currency $currency, string $description = '', ?string $order_id = null)
    {
        $this->amount = $amount;
        $this->currency = $currency;
        $this->description = $description;
        $this->order_id = $order_id;
        $this->payments = new Collection();
    }

    public function getPaymentAmount(): int
    {
        return $this->amount;
    }

    public function getPaymentCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function getPaymentDescription(): string
    {
        return $this->description;
    }

    public function getPaymentOrderId(): ?string
    {
        return $this->order_id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function setPayments(Collection $payments): self
    {
        $this->payments = $payments;
        return $this;
    }
}
