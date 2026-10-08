<?php

namespace Ulams\Cart\Http\Requests\Admin;

use Ulams\Cart\Enums\CartPermissionsEnum;
use Ulams\Cart\Rules\ProductableExistsRule;
use Ulams\Cart\Rules\ProductableRegisteredRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductableProductRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can(CartPermissionsEnum::MANAGE_PRODUCTS);
    }

    public function rules(): array
    {
        return [
            'productable_id' => ['required', new ProductableExistsRule()],
            'productable_type' => ['required', 'string', new ProductableRegisteredRule()],
        ];
    }

    public function getProductableId(): int
    {
        return $this->validated()['productable_id'];
    }

    public function getProductableType(): string
    {
        return $this->validated()['productable_type'];
    }
}
