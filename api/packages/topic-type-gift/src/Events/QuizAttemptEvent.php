<?php

namespace EscolaLms\TopicTypeGift\Events;

use EscolaLms\Auth\Models\User;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class QuizAttemptEvent
{
    use Dispatchable, SerializesModels;

    public User $user;
    public QuizAttempt $attempt;

    public function __construct(User $user, QuizAttempt $attempt)
    {
        $this->user = $user;
        $this->attempt = $attempt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAttempt(): QuizAttempt
    {
        return $this->attempt;
    }
}
