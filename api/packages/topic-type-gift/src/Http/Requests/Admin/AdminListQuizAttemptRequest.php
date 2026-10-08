<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Http\Requests\ListQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Support\Facades\Gate;

class AdminListQuizAttemptRequest extends ListQuizAttemptRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', QuizAttempt::class);
    }
}
