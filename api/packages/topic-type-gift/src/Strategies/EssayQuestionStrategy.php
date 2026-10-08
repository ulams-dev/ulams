<?php

namespace Ulams\TopicTypeGift\Strategies;

use Ulams\TopicTypeGift\Dtos\CheckAnswerDto;
use Ulams\TopicTypeGift\Enum\AnswerKeyEnum;

class EssayQuestionStrategy extends QuestionStrategy
{
    public function checkAnswer(array $answer): CheckAnswerDto
    {
        return new CheckAnswerDto();
    }

    public function getAnswerKey(): string
    {
        return AnswerKeyEnum::TEXT;
    }

    public function requiresManualGrading(): bool
    {
        return true;
    }
}
