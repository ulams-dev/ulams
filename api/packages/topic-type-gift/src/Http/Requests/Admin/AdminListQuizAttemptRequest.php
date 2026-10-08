<?php

namespace Ulams\TopicTypeGift\Http\Requests\Admin;

use Ulams\TopicTypeGift\Http\Requests\ListQuizAttemptRequest;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Support\Facades\Gate;

class AdminListQuizAttemptRequest extends ListQuizAttemptRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', QuizAttempt::class);
    }
}
