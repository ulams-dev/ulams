<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Http\Requests\ReadQuizAttemptRequest;
use Illuminate\Support\Facades\Gate;

class AdminReadQuizAttemptRequest extends ReadQuizAttemptRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getAttempt());
    }
}
