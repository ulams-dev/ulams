<?php

namespace Ulams\Auth\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;
use Ulams\Auth\Support\TokenScopes;

/** Public: the CLI has no credentials yet. Throttled by IP on the route. */
class DeviceCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1', 'max:40'],
            'scopes.*' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                $known = array_merge(TokenScopes::all(), array_map(fn ($p) => '@' . $p, array_keys(TokenScopes::PRESETS)));
                if (!in_array($value, $known, true)) {
                    $fail("Unknown scope {$value}.");
                }
            }],
            'agent' => ['nullable', 'string', 'max:100'],
        ];
    }
}
