<?php

namespace Ulams\Images\Enum;

use Ulams\Core\Enums\BasicEnum;

class ConstantEnum extends BasicEnum
{
    public const CACHE_DIRECTORY = 'imgcache';
    public const RATE_LIMIT_GLOBAL = 10000;
    public const RATE_LIMIT_PER_IP = 1000;
}
