<?php

namespace Ulams\TopicTypeGift\Http\Requests\Admin;

use Ulams\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
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
