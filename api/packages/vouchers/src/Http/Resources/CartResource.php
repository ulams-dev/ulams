<?php

namespace EscolaLms\Vouchers\Http\Resources;

use EscolaLms\Cart\Http\Resources\CartResource as BaseCartResource;
use EscolaLms\Cart\Models\Cart as BaseCart;
use EscolaLms\Vouchers\Enums\CouponTypeEnum;
use EscolaLms\Vouchers\Models\Cart;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CartResource extends BaseCartResource
{
    public function __construct(BaseCart $cart, ?int $taxRate = null)
    {
        parent::__construct($cart instanceof Cart ? $cart : Cart::find($cart->getKey()), $taxRate);
    }

    protected function getCart(): Cart
    {
        return $this->resource instanceof Cart ? $this->resource : Cart::find($this->resource->getKey());
    }

    protected function getCartItemsResourceCollection(): ResourceCollection
    {
        return CartItemResource::collection($this->getCart()->items);
    }

    /**
     * @param $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'additional_discount' => $this->getCart()->coupon && in_array($this->getCart()->coupon->type, [CouponTypeEnum::CART_FIXED, CouponTypeEnum::CART_PERCENT])
                ? $this->getCart()->cart_discount
                : $this->getCart()->getAdditionalDiscountAttribute(),
            'total_prediscount' => $this->getCart()->getTotalPreDiscountAttribute(),
            'coupon' => optional($this->getCart()->coupon)->code,
            'coupon_type' => optional($this->getCart()->coupon)->type,
        ]);
    }
}
