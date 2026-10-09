<?php

namespace Ulams\TopicTypeGift\Strategies;

use Ulams\TopicTypeGift\Dtos\CheckAnswerDto;
use Ulams\TopicTypeGift\Enum\AnswerKeyEnum;
use Illuminate\Support\Str;
use Ulams\TopicTypeGift\Support\SeededShuffle;

class MultipleChoiceQuestionStrategy extends QuestionStrategy
{
    public function getOptions(): array
    {
        $escapedQuestion = $this->escapedcharPre($this->questionPlainText);
        $answerBlock = $this->service->getAnswerFromQuestion($escapedQuestion);
        $answers = preg_split('/\s*[=~]\s*/', $answerBlock, -1, PREG_SPLIT_NO_EMPTY);

        $answers = collect($answers)
            ->map(fn(string $answer) => $this->escapedcharPost($answer))
            ->map(fn(string $answer) => $this->removeFeedbackFromAnswer($answer));

        if ($this->shouldRandomizeOptions()) {
            $answers = SeededShuffle::shuffle($answers, $this->optionsSeedFor('answers'));
        }

        return [
            'answers' => $answers->values()->toArray(),
        ];
    }

    public function checkAnswer(array $answer): CheckAnswerDto
    {
        $result = new CheckAnswerDto();

        if ($this->getCorrectAnswer() === $answer[$this->getAnswerKey()]) {
            $result->setScore($this->questionModel->score);
        }

        $result->setFeedback($this->getFeedbackByAnswer($answer[$this->getAnswerKey()]));

        return $result;
    }

    public function getCorrectAnswer(): string
    {
        $escapedQuestion = $this->escapedcharPre($this->questionPlainText);
        $answerBlock = $this->service->getAnswerFromQuestion($escapedQuestion);

        if (preg_match('/=([^~]*)/', $answerBlock, $matches)) {
            $correctAnswer = $this->escapedcharPost($matches[1]);

            return $this->removeFeedbackFromAnswer($correctAnswer);
        }

        return '';
    }

    public function getFeedbackByAnswer(string $answer): string
    {
        $answerBlock = $this->service->getAnswerFromQuestion($this->questionPlainText);

        $pattern = chr(58) . preg_quote($answer, '/') . "(.*?)(~|$)" . chr(58);
        if (preg_match($pattern, $answerBlock, $matches)) {
            return trim(Str::replace('#', ' ', $matches[1]));
        }

        return '';
    }

    public function getAnswerKey(): string
    {
        return AnswerKeyEnum::TEXT;
    }
}
