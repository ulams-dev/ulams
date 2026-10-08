<?php

namespace EscolaLms\Vouchers\Strategies\Abstracts;

use EscolaLms\Vouchers\Models\Coupon;
use EscolaLms\Vouchers\Strategies\Contracts\DiscountStrategyContract;

abstract class DiscountStrategy implements DiscountStrategyContract
{
    protected Coupon $coupon;

    public function __construct(Coupon $coupon)
    {
        $this->coupon = $coupon;
    }
}
