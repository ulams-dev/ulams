<?php

namespace Ulams\TopicTypeGift\Http\Requests\Admin;

use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Ulams\TopicTypeGift\Models\GiftQuestion;
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
