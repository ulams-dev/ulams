<?php

namespace EscolaLms\Vouchers\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class VoucherPermissionsEnum extends BasicEnum
{
    const COUPON_LIST   = 'coupon_list';
    const COUPON_CREATE = 'coupon_create';
    const COUPON_READ   = 'coupon_read';
    const COUPON_UPDATE = 'coupon_update';
    const COUPON_DELETE = 'coupon_delete';
    const COUPON_USE    = 'coupon_use';
}
