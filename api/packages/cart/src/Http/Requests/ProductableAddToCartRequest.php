<?php

namespace Ulams\Cart\Http\Requests;

use Ulams\Cart\Enums\CartPermissionsEnum;
use Ulams\Cart\Rules\ProductableExistsRule;
use Ulams\Cart\Rules\ProductableRegisteredRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductableAddToCartRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can(CartPermissionsEnum::BUY_PRODUCTS);
    }

    public function rules(): array
    {
        return [
            'productable_id' => ['required', 'integer', new ProductableExistsRule()],
            'productable_type' => ['required', 'string', new ProductableRegisteredRule()]
        ];
    }

    public function getProductableId(): int
    {
        return $this->input('productable_id');
    }

    public function getProductableType(): string
    {
        return $this->input('productable_type');
    }
}
