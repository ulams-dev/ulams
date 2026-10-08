<?php

namespace EscolaLms\Vouchers\Database\Factories;

use EscolaLms\Vouchers\Enums\CouponTypeEnum;
use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CouponFactory extends Factory
{

    protected $model = Coupon::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'code' => Str::random(10),
            'active' => true,
            'active_from' => null,
            'active_to' => null,
            'limit_usage' => null,
            'limit_per_user' => null,
            'min_cart_price' => null,
            'max_cart_price' => null,
            'amount' => 10,
            'type' => CouponTypeEnum::PRODUCT_PERCENT,
            'exclude_promotions' => true,
        ];
    }

    public function cart_percent()
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponTypeEnum::CART_PERCENT,
            'amount' => 10,
        ]);
    }

    public function cart_fixed()
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponTypeEnum::CART_FIXED,
            'amount' => 1000,
        ]);
    }

    public function product_percent()
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponTypeEnum::PRODUCT_PERCENT,
            'amount' => 10,
        ]);
    }

    public function product_fixed()
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponTypeEnum::PRODUCT_FIXED,
            'amount' => 1000,
        ]);
    }
}
