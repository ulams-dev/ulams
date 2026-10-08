<?php

namespace EscolaLms\TopicTypeGift\Dtos;

class CheckAnswerDto
{
    private float $score;
    private string $feedback;

    public function __construct(float $score = 0, string $feedback = '')
    {
        $this->score = $score;
        $this->feedback = $feedback;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function setFeedback(string $feedback): self
    {
        $this->feedback = $feedback;
        return $this;
    }

    public function addFeedback(string $feedback): self
    {
        $this->feedback = $this->feedback . "\n" . $feedback;
        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getFeedback(): string
    {
        return $this->feedback;
    }
}
