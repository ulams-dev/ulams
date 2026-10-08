<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;
use Illuminate\Support\Collection;

class MatchingQuestionStrategy extends QuestionStrategy
{
    public function getOptions(): array
    {
        $answers = $this->getCorrectAnswers();

        return [
            'sub_questions' => $this->shuffleColumn($answers->map(fn($answer) => $answer[0]), 'sub_questions'),
            'sub_answers' => $this->shuffleColumn($answers->map(fn($answer) => $answer[1]), 'sub_answers'),
        ];
    }

    /**
     * Matching columns are always presented shuffled (otherwise the pairs would
     * line up). When the quiz randomizes order the shuffle is seeded so it stays
     * stable for a given attempt; otherwise it stays random per request as before.
     * A distinct salt per column keeps the two columns from lining up.
     */
    private function shuffleColumn(Collection $items, string $salt): array
    {
        $items = $this->shouldRandomizeOptions()
            ? $items->shuffle($this->optionsSeedFor($salt))
            : $items->shuffle();

        return $items->values()->toArray();
    }

    public function checkAnswer(array $answer): CheckAnswerDto
    {
        $correctAnswers = $this->getCorrectAnswers();
        $answer = $answer[$this->getAnswerKey()];
        $result = new CheckAnswerDto();

        foreach ($correctAnswers as $correctAnswer) {
            if ($answer[$correctAnswer[0]] != $correctAnswer[1])
                return $result->setScore(0);
        }

        return $result->setScore($this->questionModel->score);
    }

    private function getCorrectAnswers(): Collection
    {
        $escapedQuestion = $this->escapedcharPre($this->questionPlainText);
        $answerBlock = $this->service->getAnswerFromQuestion($escapedQuestion);
        $answers = preg_split('/\s*=\s*/', $answerBlock, -1, PREG_SPLIT_NO_EMPTY);

        return collect($answers)
            ->map(fn($answer) => $this->escapedcharPost($answer))
            ->map(fn($answer) => $this->removeFeedbackFromAnswer($answer))
            ->map(fn($answer) => explode('->', $answer))
            ->map(fn($answer) => [trim($answer[0]), trim($answer[1])]);
    }

    public function getAnswerKey(): string
    {
        return AnswerKeyEnum::MATCHING;
    }
}
