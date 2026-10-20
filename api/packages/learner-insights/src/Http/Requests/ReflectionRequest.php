<?php

namespace Ulams\LearnerInsights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReflectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['text' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
