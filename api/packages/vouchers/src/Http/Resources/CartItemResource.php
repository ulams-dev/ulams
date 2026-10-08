<?php

namespace EscolaLms\Vouchers\Http\Resources;

use EscolaLms\Cart\Http\Resources\CartItemResource as BaseCartItemResource;
use EscolaLms\Cart\Models\CartItem as BaseCartItem;
use EscolaLms\Vouchers\Models\CartItem;

class CartItemResource extends BaseCartItemResource
{
    public function __construct(BaseCartItem $cartItem)
    {
        $cartItem = $cartItem instanceof CartItem ? $cartItem : CartItem::find($cartItem->getKey());
        parent::__construct($cartItem);
    }

    protected function getCartItem(): CartItem
    {
        return $this->resource;
    }

    /**
     * @param $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'discount' => $this->getCartItem()->getDiscountAttribute()
        ]);
    }
}
