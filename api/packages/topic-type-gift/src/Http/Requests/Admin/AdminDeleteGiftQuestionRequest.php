<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminDeleteGiftQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->getTopic() && !Gate::allows('delete', $this->getTopic())) {
            return false;
        }

        return $this->user()->can(TopicTypeGiftPermissionEnum::DELETE_GIFT_QUIZ_QUESTION);
    }

    public function rules(): array
    {
        return [];
    }

    public function getId(): int
    {
        return $this->route('id');
    }

    public function getTopic(): ?Topic
    {
        return GiftQuestion::findOrFail($this->getId())->giftQuiz->topic;
    }
}
