<?php

namespace Ulams\ModelFields\Enum;

use Ulams\Core\Enums\BasicEnum;

class MetaFieldTypeEnum extends BasicEnum
{
    const BOOLEAN   = 'boolean';
    const NUMBER   = 'number';
    const VARCHAR   = 'varchar';
    const TEXT  = 'text';
    const JSON  = 'json';
}
