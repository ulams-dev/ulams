<?php

namespace Ulams\Recommender\Enum;

use Ulams\Core\Enums\BasicEnum;

class SatisfactionStatusEnum extends BasicEnum
{
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const FAILED = 'failed';
}
