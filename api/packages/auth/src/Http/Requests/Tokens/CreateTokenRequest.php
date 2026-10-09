<?php

namespace Ulams\Auth\Http\Requests\Tokens;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Rules\NoHtmlTags;
use Ulams\Auth\Support\TokenScopes;

class CreateTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', ApiTokenMeta::class);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new NoHtmlTags()],
            'scopes' => ['required', 'array', 'min:1', 'max:40'],
            'scopes.*' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                $known = array_merge(TokenScopes::all(), array_map(fn ($p) => '@' . $p, array_keys(TokenScopes::PRESETS)));
                if (!in_array($value, $known, true)) {
                    $fail("Unknown scope {$value}.");
                }
            }],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:' . TokenScopes::maxDays($this->getHost())],
            'kind' => ['nullable', Rule::in(ApiTokenMeta::KINDS)],
            'agent_name' => ['nullable', 'string', 'max:100', new NoHtmlTags()],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:6000'],
        ];
    }
}
