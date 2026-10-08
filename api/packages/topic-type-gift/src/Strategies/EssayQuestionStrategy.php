<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;

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
