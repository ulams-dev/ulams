<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Illuminate\Support\Facades\Gate;

class AdminUpdateGiftQuestionRequest extends AdminGiftQuestionRequest
{
    public function authorize(): bool
    {
        if ($this->getTopic() && !Gate::allows('update', $this->getTopic())) {
            return false;
        }
        return $this->user()->can(TopicTypeGiftPermissionEnum::UPDATE_GIFT_QUIZ_QUESTION);
    }

    public function getId(): int
    {
        return $this->route('id');
    }
}
