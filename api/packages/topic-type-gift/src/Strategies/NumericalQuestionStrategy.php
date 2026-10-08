<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;
use Illuminate\Support\Str;

class NumericalQuestionStrategy extends QuestionStrategy
{
    public function checkAnswer(array $answer): CheckAnswerDto
    {
        $answerBlock = $this->service->getAnswerFromQuestion($this->questionPlainText);
        $answerBlock = Str::between($answerBlock, '#', '#');
        $result = new CheckAnswerDto();

        $answer = (float)$answer[$this->getAnswerKey()];

        if (Str::contains($answerBlock, ':')) {
            $base = (float) Str::before($answerBlock, ':');
            $range = (float) Str::after($answerBlock, ':');
            $min = $base - $range;
            $max = $base + $range;

            if ($answer >= $min && $answer <= $max) {
                return $result->setScore($this->questionModel->score);
            }
            return $result;
        }

        if (Str::contains($answerBlock, '..')) {
            $min = (float) Str::before($answerBlock, '..');
            $max = (float) Str::after($answerBlock, '..');

            if ($answer >= $min && $answer <= $max) {
                return $result->setScore($this->questionModel->score);
            }
            return $result;
        }

        if ($answer === (float) $answerBlock) {
            return $result->setScore($this->questionModel->score);
        }

        return $result;
    }

    public function getAnswerKey(): string
    {
        return AnswerKeyEnum::NUMERIC;
    }
}
