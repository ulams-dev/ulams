<?php

namespace Ulams\Auth\Http\Requests\Tokens;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Auth\Models\ApiTokenMeta;

class ListTokensRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('viewAny', ApiTokenMeta::class);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['include_revoked' => ['nullable', 'boolean']];
    }
}
