<?php

namespace EscolaLms\TopicTypeGift\Http\Requests;

use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EndQuizAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('readOwn', $this->getAttempt());
    }

    public function rules(): array
    {
        return [];
    }

    public function getId(): int
    {
        return $this->route('id');
    }

    private function getAttempt(): QuizAttempt
    {
        return QuizAttempt::findOrFail($this->getId());
    }
}
