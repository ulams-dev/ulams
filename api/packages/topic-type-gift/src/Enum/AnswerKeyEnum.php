<?php

namespace Ulams\TopicTypeGift\Enum;

use Ulams\Core\Enums\BasicEnum;

class AnswerKeyEnum extends BasicEnum
{
    public const TEXT = 'text';
    public const MATCHING = 'matching';
    public const MULTIPLE = 'multiple';
    public const NUMERIC = 'numeric';
    public const BOOL = 'bool';
}
