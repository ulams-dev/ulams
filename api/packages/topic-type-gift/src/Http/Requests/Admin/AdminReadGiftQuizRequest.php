<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminReadGiftQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getGiftQuiz());
    }

    public function rules(): array
    {
        return [];
    }

    public function getGiftQuiz(): GiftQuiz
    {
        return GiftQuiz::findOrFail($this->route('id'));
    }
}
