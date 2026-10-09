<?php

namespace Ulams\Auth\Http\Requests\Tokens;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ulams\Auth\Models\ApiTokenMeta;

class AdminListTokensRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage', ApiTokenMeta::class);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'min:1'],
            'kind' => ['nullable', Rule::in(ApiTokenMeta::KINDS)],
            'include_revoked' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
