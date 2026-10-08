<?php

namespace Ulams\TopicTypeGift\Strategies;

use Ulams\TopicTypeGift\Dtos\CheckAnswerDto;

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
