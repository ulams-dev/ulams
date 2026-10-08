<?php

namespace EscolaLms\MailerLite\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class GroupNamesEnum extends BasicEnum
{
    public const REGISTERED_USERS  = 'Registered users';
    public const ORDER_PAID        = 'Users with paid orders';
    public const LEFT_CART         = 'Left cart';
}
