<?php

namespace Ulams\LearnerInsights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'help' => ['sometimes', 'boolean'],
            'nudges' => ['sometimes', 'boolean'],
            'consent' => ['sometimes', 'boolean'],
            // with `enabled: false`: also delete what was recorded before
            'eraseExisting' => ['sometimes', 'boolean'],
        ];
    }
}
