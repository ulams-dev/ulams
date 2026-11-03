<?php

namespace Ulams\Commerce\Enums;

use Ulams\Core\Enums\BasicEnum;

class CommercePermissionsEnum extends BasicEnum
{
    /** Set prices, take sellables on and off sale, create bundles and access passes. */
    const PRICES_MANAGE = 'commerce_prices_manage';
}
