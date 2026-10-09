<?php

namespace Ulams\Auth\Http\Requests\Tokens;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Auth\Models\ApiTokenMeta;

class AgentAuditListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage', ApiTokenMeta::class);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'token_id' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
