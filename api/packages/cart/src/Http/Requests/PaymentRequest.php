<?php

namespace Ulams\Cart\Http\Requests;

use Ulams\Cart\Dtos\ClientDetailsDto;
use Ulams\Cart\Enums\CartPermissionsEnum;
use Ulams\Cart\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(CartPermissionsEnum::BUY_PRODUCTS);
    }

    public function rules(): array
    {
        return [
            'client_name' => ['sometimes', 'string'],
            'client_email' => ['sometimes', 'email'],
            'client_street' => ['sometimes', 'string'],
            'client_street_number' => ['sometimes', 'string'],
            'client_postal' => ['sometimes', 'string'],
            'client_city' => ['sometimes', 'string'],
            'client_country' => ['sometimes', 'string'],
            'client_company' => ['sometimes', 'string'],
            'client_taxid' => ['sometimes', 'string', 'required_with:client_company'],
        ];
    }

    public function toClientDetailsDto(): ClientDetailsDto
    {
        return new ClientDetailsDto(
            $this->input('client_name'),
            $this->input('client_email'),
            $this->input('client_street'),
            $this->input('client_street_number'),
            $this->input('client_city'),
            $this->input('client_postal'),
            $this->input('client_country'),
            $this->input('client_company'),
            $this->input('client_taxid')
        );
    }

    /**
     * Client fields that may reach the payment drivers (what the drivers read: `gateway`, `payment_method`,
     * `return_url`, `email`, `channel`). Everything else is dropped, so a client can never set the currency,
     * the amount, trial/refund flags or subscription terms; the server supplies those.
     */
    public const PAYMENT_PARAMETERS_ALLOW_LIST = [
        'gateway',
        'payment_method',
        'return_url',
        'email',
        'channel',
    ];

    public function getAdditionalPaymentParameters(): array
    {
        return $this->only(self::PAYMENT_PARAMETERS_ALLOW_LIST);
    }

    public function getCartUser(): User
    {
        return User::findOrFail($this->user()->getKey());
    }
}
