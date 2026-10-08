<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminListGiftQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', GiftQuiz::class);
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer'],
        ];
    }

    public function getCourseId(): int
    {
        return (int) $this->input('course_id');
    }
}
