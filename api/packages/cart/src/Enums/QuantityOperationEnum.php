<?php

namespace Ulams\Cart\Enums;

use Ulams\Core\Enums\BasicEnum;

class QuantityOperationEnum extends BasicEnum
{
    public const INCREMENT = 'increment';
    public const DECREMENT = 'decrement';
    public const UNCHANGED = 'unchanged';
}
