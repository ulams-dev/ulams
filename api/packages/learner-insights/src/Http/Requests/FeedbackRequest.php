<?php

namespace Ulams\LearnerInsights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['helpful' => ['required', 'boolean']];
    }
}
