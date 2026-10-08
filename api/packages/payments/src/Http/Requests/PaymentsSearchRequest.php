<?php

namespace EscolaLms\Payments\Http\Requests;

use BenSampo\Enum\Rules\EnumValue;
use EscolaLms\Payments\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentsSearchRequest extends FormRequest
{
    public function authorize()
    {
        return !empty($this->user());
    }

    public function rules()
    {
        return [
            'status' => ['nullable', new EnumValue(PaymentStatus::class, false)],
            'payable_id' => ['sometimes', 'integer'],
            'payable_type' => ['sometimes', 'string'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'order_id' => ['sometimes', 'string'],
            'order_by' => ['sometimes', Rule::in(['created_at', 'updated_at', 'status', 'payable_id', 'user_id', 'amount', 'order_id', 'id'])],
            'per_page' => ['sometimes', 'integer'],
        ];
    }

    protected function passedValidation()
    {
        $user = $this->user();
        $this->merge([
            'user_id' => $user->getKey(),
        ]);
    }
}
