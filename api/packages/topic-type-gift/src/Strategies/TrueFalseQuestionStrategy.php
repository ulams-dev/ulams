<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;
use Illuminate\Support\Str;

class TrueFalseQuestionStrategy extends QuestionStrategy
{
    public function checkAnswer(array $answer): CheckAnswerDto
    {
        $answerBlock = $this->service->getAnswerFromQuestion($this->questionPlainText);
        $correctAnswer = $this->removeFeedbackFromAnswer($answerBlock);
        $result = new CheckAnswerDto();

        $answer = (bool) $answer[$this->getAnswerKey()];

        if ((($correctAnswer === 'TRUE' || $correctAnswer === 'T') && $answer) ||
            (($correctAnswer === 'FALSE' || $correctAnswer === 'F') && !$answer))
        {
            return $result->setScore($this->questionModel->score);
        }

        return $result;
    }

    public function getAnswerKey(): string
    {
        return AnswerKeyEnum::BOOL;
    }
}
