<?php

namespace EscolaLms\Vouchers\Exceptions;

use Exception;

class CouponInactiveException extends Exception
{

    public function __construct()
    {
        parent::__construct(__('Coupon is no longer active'));
    }
}
