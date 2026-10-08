<?php

namespace EscolaLms\TopicTypeGift\Http\Requests;

use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReadQuizAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('readOwn', $this->getAttempt());
    }

    public function rules(): array
    {
        return [];
    }

    public function getAttempt(): QuizAttempt
    {
        return QuizAttempt::findOrFail($this->route('id'));
    }
}
