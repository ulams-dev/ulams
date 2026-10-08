<?php

namespace Ulams\ModelFields\Enum;

use Ulams\Core\Enums\BasicEnum;

class MetaFieldVisibilityEnum extends BasicEnum
{
    const PUBLIC        = 1 << 0;
    const AUTHORIZED    = 1 << 1;
    const ADMIN         = 1 << 2;
}
