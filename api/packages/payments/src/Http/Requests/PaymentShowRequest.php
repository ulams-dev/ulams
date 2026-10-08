<?php

namespace EscolaLms\Payments\Http\Requests;

use EscolaLms\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class PaymentShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('view', $this->getPayment());
    }

    public function getPayment(): Payment
    {
        $payment = $this->route('payment');
        if (!$payment instanceof Payment) {
            $payment = Payment::find($payment);
        }
        return $payment;
    }

    public function rules(): array
    {
        return [];
    }
}
