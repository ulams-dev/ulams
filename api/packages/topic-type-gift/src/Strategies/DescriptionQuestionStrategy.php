<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;

class DescriptionQuestionStrategy extends QuestionStrategy
{
    public function checkAnswer(array $answer): CheckAnswerDto
    {
        return new CheckAnswerDto();
    }

    public function getAnswerKey(): ?string
    {
        return null;
    }
}
