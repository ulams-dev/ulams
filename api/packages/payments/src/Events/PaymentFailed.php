<?php

namespace EscolaLms\Payments\Events;

use EscolaLms\Core\Models\User;
use EscolaLms\Payments\Models\Payment;

class PaymentFailed extends PaymentEvent
{
    private ?string $code;
    private ?string $message;

    public function __construct(User $user, Payment $payment, ?string $code = null, ?string $message = null)
    {
        $this->code = $code;
        $this->message = $message;
        parent::__construct($user, $payment);
    }

    /**
     * Get the value of code
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * Get the value of message
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
