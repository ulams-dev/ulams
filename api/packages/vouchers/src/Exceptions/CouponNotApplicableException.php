<?php

namespace EscolaLms\Vouchers\Exceptions;

use Exception;

class CouponNotApplicableException extends Exception
{

    public function __construct()
    {
        parent::__construct(__('Coupon can not be applied to this Cart'));
    }
}
