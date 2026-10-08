<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;
use Illuminate\Support\Str;

class ShortAnswerQuestionStrategy extends QuestionStrategy
{
    public function checkAnswer(array $answer): CheckAnswerDto
    {
        $answer = $answer[$this->getAnswerKey()];
        $result = new CheckAnswerDto();

        $escapedQuestion = $this->escapedcharPre($this->questionPlainText);
        $answerBlock = $this->service->getAnswerFromQuestion($escapedQuestion);
        $allAnswers = preg_split('/\s*=\s*/', $answerBlock, -1, PREG_SPLIT_NO_EMPTY);

        $allCorrectAnswers = collect($allAnswers)->map(function ($answer) {
            $value = trim(Str::beforeLast(Str::afterLast($answer, '%'), '#'));
            $value = $this->escapedcharPost($value);

            return [
                'value' => $value,
                'feedback' => Str::contains($answer, '#') ? Str::after( $answer, '#') : '',
                'percent' => Str::contains($answer, '%') ? (float) Str::between($answer, '%', '%') : 100,
            ];
        });

        $found = $allCorrectAnswers->firstWhere('value', $answer);

        if ($found) {
            $result->setScore($this->questionModel->score * $found['percent'] * 0.01)
                ->setFeedback($found['feedback']);
        }

        return $result;
    }

    public function getAnswerKey(): string
    {
        return AnswerKeyEnum::TEXT;
    }
}
