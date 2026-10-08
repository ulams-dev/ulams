<?php

namespace Ulams\Cart\Enums;

use Ulams\Core\Enums\BasicEnum;

class SubscriptionStatus extends BasicEnum
{
    public const ACTIVE = 'active';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
}
