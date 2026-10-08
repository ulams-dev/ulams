<?php

namespace Ulams\Payments\Enums;

use Ulams\Core\Enums\BasicEnum;

class PaymentsPermissionsEnum extends BasicEnum
{
    const PAYMENTS_LIST = 'payment_list';
    const PAYMENTS_READ = 'payment_read';
    const PAYMENTS_EXPORT = 'payment_export';
}
