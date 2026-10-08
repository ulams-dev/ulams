<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MultipleChoiceWithMultipleAnswersQuestionStrategy extends QuestionStrategy
{
    public function getOptions(): array
    {
        $answers = collect($this->getMappedAnswers())->pluck('value');

        if ($this->shouldRandomizeOptions()) {
            $answers = $answers->shuffle($this->optionsSeedFor('answers'));
        }

        return [
            'answers' => $answers->values()->toArray(),
        ];
    }

    public function checkAnswer(array $answer): CheckAnswerDto
    {
        $allAnswers = $this->getMappedAnswers();
        $percent = 0;
        $result = new CheckAnswerDto();

        foreach ($answer[$this->getAnswerKey()] as $item) {
            $found = $allAnswers->firstWhere('value', $item);
            if ($found) {
                $percent += $found['percent'];
                $found['feedback'] ?? $result->addFeedback($item . ' -> ' . $found['feedback']);
            }
        }

        $score = $this->questionModel->score * $percent * 0.01;
        $result->setScore($score > $this->questionModel->score ? $this->questionModel->score : $score);

        return $result;
    }

    public function getAnswerKey(): string
    {
        return AnswerKeyEnum::MULTIPLE;
    }

    private function getMappedAnswers(): Collection
    {
        $answerBlock = $this->service->getAnswerFromQuestion($this->questionPlainText);
        $answerBlock = preg_replace('/\s+/', ' ', $answerBlock);
        $answerBlock = $this->escapedcharPre($answerBlock);
        preg_match_all('/[~=][^~=]*/', $answerBlock, $matches);
        $allAnswers = $matches[0];

        return collect($allAnswers)->map(function ($answer) {
            $value = trim(Str::before(preg_replace('/%.*%/', '', substr($answer, 1)), '#'));
            $value = $this->escapedcharPost($value);

            return [
                'value' => $value,
                'feedback' => Str::contains($answer, '#') ? Str::after($answer, '#') : '',
                'percent' => Str::startsWith($answer, '=') ? 100 : (float) Str::between($answer, '%', '%'),
            ];
        });
    }
}
