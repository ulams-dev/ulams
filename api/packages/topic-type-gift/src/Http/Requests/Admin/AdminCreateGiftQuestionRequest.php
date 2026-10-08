<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Illuminate\Support\Facades\Gate;

class AdminCreateGiftQuestionRequest extends AdminGiftQuestionRequest
{
    public function authorize(): bool
    {
        if ($this->getTopic() && !Gate::allows('update', $this->getTopic())) {
            return false;
        }

        return $this->user()->can(TopicTypeGiftPermissionEnum::CREATE_GIFT_QUIZ_QUESTION);
    }
}
