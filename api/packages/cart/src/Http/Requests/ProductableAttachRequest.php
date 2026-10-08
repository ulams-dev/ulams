<?php

namespace Ulams\Cart\Http\Requests;

use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\User;
use Ulams\Cart\Rules\ProductableExistsRule;
use Ulams\Cart\Rules\ProductableRegisteredRule;
use Illuminate\Support\Facades\Gate;

class ProductableAttachRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return Gate::allows('attachToProduct', Product::class);
    }

    public function rules(): array
    {
        return [
            'productable_id' => ['required', new ProductableExistsRule()],
            'productable_type' => ['required', 'string', new ProductableRegisteredRule()]
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
