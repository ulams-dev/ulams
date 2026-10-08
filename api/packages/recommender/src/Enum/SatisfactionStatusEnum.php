<?php

namespace EscolaLms\Recommender\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class SatisfactionStatusEnum extends BasicEnum
{
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const FAILED = 'failed';
}
