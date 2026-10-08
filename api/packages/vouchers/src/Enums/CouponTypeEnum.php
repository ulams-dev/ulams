<?php

namespace EscolaLms\Vouchers\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class CouponTypeEnum extends BasicEnum
{
    const CART_FIXED      = 'cart_fixed';
    const CART_PERCENT    = 'cart_percent';
    const PRODUCT_FIXED   = 'product_fixed';
    const PRODUCT_PERCENT = 'product_percent';
}
