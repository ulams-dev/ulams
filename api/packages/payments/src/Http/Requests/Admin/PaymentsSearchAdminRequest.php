<?php

namespace EscolaLms\Payments\Http\Requests\Admin;

use BenSampo\Enum\Rules\EnumValue;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Payments\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentsSearchAdminRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->hasRole(UserRole::ADMIN);
    }

    public function rules()
    {
        return [
            'status' => ['nullable', new EnumValue(PaymentStatus::class, false)],
            'payable_id' => ['sometimes', 'integer'],
            'payable_type' => ['sometimes', 'string'],
            'user_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'order_id' => ['sometimes', 'string'],
            'order_by' => ['sometimes', Rule::in(['created_at', 'updated_at', 'status', 'payable_id', 'user_id', 'amount', 'order_id', 'id'])],
            'per_page' => ['sometimes', 'integer'],
        ];
    }
}
