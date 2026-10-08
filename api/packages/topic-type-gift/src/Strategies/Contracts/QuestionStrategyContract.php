<?php

namespace EscolaLms\TopicTypeGift\Strategies\Contracts;

use EscolaLms\TopicTypeGift\Dtos\CheckAnswerDto;

interface QuestionStrategyContract
{
    public function getTitle(): string;
    public function getQuestionForStudent(): string;
    public function getOptions(): array;
    public function checkAnswer(array $answer): CheckAnswerDto;
    public function getAnswerKey(): ?string;

    /**
     * Whether an answer to this question needs manual grading by a lecturer
     * (i.e. it is not scored automatically on submission).
     */
    public function requiresManualGrading(): bool;
}
