<?php

namespace EscolaLms\Payments\Http\Responses;

use EscolaLms\Payments\Http\Resources\PaymentResource;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Contracts\Support\Responsable;

class PaymentResponse implements Responsable
{
    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function toResponse($request)
    {
        return PaymentResource::make($this->payment)->toResponse($request);
    }
}
