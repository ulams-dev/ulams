<?php

namespace Ulams\Cart\Http\Requests;

use Ulams\Cart\Enums\CartPermissionsEnum;
use Ulams\Cart\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMissingProductsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can(CartPermissionsEnum::BUY_PRODUCTS);
    }

    public function rules(): array
    {
        return [
            'products' => ['array'],
            'products.*' => ['integer', Rule::exists(Product::class, 'id')],
        ];
    }
}
