<?php

namespace EscolaLms\Payments\Events;

use EscolaLms\Core\Models\User;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class PaymentEvent
{
    use Dispatchable, SerializesModels;

    private Payment $payment;
    private User $user;

    public function __construct(User $user, Payment $payment)
    {
        $this->payment = $payment;
        $this->user = $user;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
