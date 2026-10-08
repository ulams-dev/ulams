<?php

namespace Ulams\Questionnaire\Enums;

use Ulams\Core\Enums\BasicEnum;

class QuestionAnswersRequiredRateEnum extends BasicEnum
{
    const RATE_REQUIRED = [
        QuestionTypeEnum::RATE,
        QuestionTypeEnum::REVIEW,
    ];
}
