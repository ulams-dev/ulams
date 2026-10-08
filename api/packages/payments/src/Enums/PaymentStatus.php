<?php

namespace EscolaLms\Payments\Enums;

use EscolaLms\Core\Enums\BasicEnum;

/**
 * @method static static NEW()
 * @method static static PAID()
 * @method static static CANCELLED()
 * @method static static FAILED()
 * @method static static REQUIRES_REDIRECT()
 * @method static static REFUNDED()
 */
class PaymentStatus extends BasicEnum
{
    public const NEW               = 'new';
    public const PAID              = 'paid';
    public const CANCELLED         = 'cancelled';
    public const FAILED            = 'failed';
    public const REQUIRES_REDIRECT = 'redirect';
    public const REFUNDED          = 'refunded';
}
