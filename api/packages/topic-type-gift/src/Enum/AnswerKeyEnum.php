<?php

namespace EscolaLms\TopicTypeGift\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class AnswerKeyEnum extends BasicEnum
{
    public const TEXT = 'text';
    public const MATCHING = 'matching';
    public const MULTIPLE = 'multiple';
    public const NUMERIC = 'numeric';
    public const BOOL = 'bool';
}
