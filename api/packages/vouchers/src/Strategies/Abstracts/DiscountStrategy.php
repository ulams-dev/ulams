<?php

namespace Ulams\Vouchers\Strategies\Abstracts;

use Ulams\Vouchers\Models\Coupon;
use Ulams\Vouchers\Strategies\Contracts\DiscountStrategyContract;

abstract class DiscountStrategy implements DiscountStrategyContract
{
    protected Coupon $coupon;

    public function __construct(Coupon $coupon)
    {
        $this->coupon = $coupon;
    }
}
