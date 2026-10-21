<?php

namespace Ulams\Tutor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:2', 'max:' . (int) config('tutor.limits.question_chars')],
            'conversationId' => ['sometimes', 'nullable', 'string', 'size:26'],
            'topicId' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
