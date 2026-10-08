<?php

namespace Ulams\Vouchers\Http\Resources;

use Ulams\Vouchers\Models\CouponProduct;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponProductResource extends JsonResource
{
    public function __construct(CouponProduct $couponProduct)
    {
        parent::__construct($couponProduct);
    }

    /**
     * @param $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'coupon_id' => $this->resource->coupon_id,
            'product_id' => $this->resource->product_id,
        ];
    }
}
