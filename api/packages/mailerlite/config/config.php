<?php

use EscolaLms\MailerLite\Enum\GroupNamesEnum;
use EscolaLms\MailerLite\Enum\PackageStatusEnum;

return [
    'package_status' => PackageStatusEnum::DISABLED,
    'api_key' => null,
    'newsletter_field_key' => 'newsletter',
    'group_registered_group' => GroupNamesEnum::REGISTERED_USERS,
    'group_order_paid' => GroupNamesEnum::ORDER_PAID,
    'group_left_cart' => GroupNamesEnum::LEFT_CART,
];
