<?php

namespace Ulams\Vouchers\Http\Resources;

use Ulams\Cart\Http\Resources\CartItemResource as BaseCartItemResource;
use Ulams\Cart\Models\CartItem as BaseCartItem;
use Ulams\Vouchers\Models\CartItem;

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
