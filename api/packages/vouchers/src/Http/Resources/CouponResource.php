<?php

namespace EscolaLms\Vouchers\Http\Resources;

use EscolaLms\Cart\Http\Resources\ProductResource;
use EscolaLms\Categories\Http\Resources\CategoryResource;
use EscolaLms\Core\Models\User;
use EscolaLms\Vouchers\Models\Coupon;
use EscolaLms\Vouchers\Services\Contracts\CouponServiceContract;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function __construct(Coupon $coupon)
    {
        parent::__construct($coupon);
    }

    /**
     * @param $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $couponService = app(CouponServiceContract::class);
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'type' => $this->resource->type,
            'active' => $this->resource->active,
            'active_from' => $this->resource->active_from,
            'active_to' => $this->resource->active_to,
            'limit_usage' => $this->resource->limit_usage,
            'limit_per_user' => $this->resource->limit_per_user,
            'min_cart_price' => $this->resource->min_cart_price,
            'max_cart_price' => $this->resource->max_cart_price,
            'amount' => $this->resource->amount,
            'included_products' => ProductResource::collection($this->resource->includedProducts),
            'excluded_products' => ProductResource::collection($this->resource->excludedProducts),
            'users' => $this->resource->users->map(fn (User $user) => $user->getKey())->toArray(),
            'included_categories' => CategoryResource::collection($this->resource->includedCategories),
            'excluded_categories' => CategoryResource::collection($this->resource->excludedCategories),
            'exclude_promotions' => $this->resource->exclude_promotions,
            'usages' => $couponService->couponTimesUsed($this->resource),
        ];
    }
}
