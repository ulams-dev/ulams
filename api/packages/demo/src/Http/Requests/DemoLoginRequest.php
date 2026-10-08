<?php

namespace Ulams\Demo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ulams\Demo\Enums\DemoRole;

class DemoLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(DemoRole::values())],
        ];
    }

    public function demoRole(): DemoRole
    {
        return DemoRole::from((string) $this->validated('role'));
    }
}
