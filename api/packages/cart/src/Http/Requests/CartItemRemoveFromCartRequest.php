<?php

namespace Ulams\Cart\Http\Requests;

use Ulams\Cart\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartItemRemoveFromCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return !!$this->user();
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'id' => $this->route('id')
        ]);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', Rule::exists(CartItem::class, 'id')],
        ];
    }

    public function getCartItemId(): int
    {
        /** @var int $id */
        $id = $this->route('id');
        return $id;
    }
}
