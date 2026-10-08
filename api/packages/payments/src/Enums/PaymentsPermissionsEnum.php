<?php

namespace EscolaLms\Payments\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class PaymentsPermissionsEnum extends BasicEnum
{
    const PAYMENTS_LIST = 'payment_list';
    const PAYMENTS_READ = 'payment_read';
    const PAYMENTS_EXPORT = 'payment_export';
}
