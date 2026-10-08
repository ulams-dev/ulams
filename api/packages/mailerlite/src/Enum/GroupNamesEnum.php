<?php

namespace Ulams\MailerLite\Enum;

use Ulams\Core\Enums\BasicEnum;

class GroupNamesEnum extends BasicEnum
{
    public const REGISTERED_USERS  = 'Registered users';
    public const ORDER_PAID        = 'Users with paid orders';
    public const LEFT_CART         = 'Left cart';
}
