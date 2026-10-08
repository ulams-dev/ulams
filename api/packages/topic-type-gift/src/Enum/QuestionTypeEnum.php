<?php

namespace EscolaLms\TopicTypeGift\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class QuestionTypeEnum extends BasicEnum
{
    public const MULTIPLE_CHOICE = 'multiple_choice';
    public const MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS = 'multiple_choice_with_multiple_right_answers';
    public const TRUE_FALSE = 'true_false';
    public const SHORT_ANSWERS = 'short_answers';
    public const MATCHING = 'matching';
    public const NUMERICAL_QUESTION = 'numerical_question';
    public const ESSAY = 'essay';
    public const DESCRIPTION = 'description';
}
