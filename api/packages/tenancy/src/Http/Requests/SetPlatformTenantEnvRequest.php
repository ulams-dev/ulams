<?php

namespace Ulams\Tenancy\Http\Requests;

use Closure;
use Illuminate\Validation\Validator;
use Ulams\Tenancy\Support\TenantNaming;

class SetPlatformTenantEnvRequest extends PlatformRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        $allowed = TenantNaming::inheritableKeys();
        $key = function (string $attribute, mixed $value, Closure $fail) use ($allowed) {
            if (!in_array(strtoupper(trim((string) $value)), $allowed, true)) {
                $fail('Only these settings can be set per tenant: ' . implode(', ', $allowed) . '.');
            }
        };

        return [
            'set' => ['nullable', 'array', 'max:20'],
            'set.*' => ['required', 'string', 'max:500'],
            'unset' => ['nullable', 'array', 'max:20'],
            'unset.*' => ['required', 'string', $key],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $allowed = TenantNaming::inheritableKeys();
        $validator->after(function (Validator $v) use ($allowed) {
            foreach (array_keys((array) $this->input('set', [])) as $name) {
                if (!in_array(strtoupper(trim((string) $name)), $allowed, true)) {
                    $v->errors()->add('set', "Only these settings can be set per tenant: " . implode(', ', $allowed) . '.');
                }
            }
            if (empty($this->input('set')) && empty($this->input('unset'))) {
                $v->errors()->add('set', 'Send `set` (KEY: value) or `unset` (a list of keys).');
            }
        });
    }
}
