<?php

namespace Ulams\Tenancy\Http\Requests;

use Closure;
use Ulams\Tenancy\Services\TenantTemplates;
use Ulams\Tenancy\Support\TenantNaming;

class SavePlatformTemplateRequest extends PlatformRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'regex:' . TenantTemplates::SLUG_PATTERN],
            'name' => ['nullable', 'string', 'max:100', 'regex:/^[^<>]*$/'],
            'theme' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{1,40}$/'],
            'accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'demo' => ['nullable', 'boolean'],
            'users' => ['nullable', 'integer', 'min:0', 'max:50'],
            'env' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) {
                $allowed = TenantNaming::inheritableKeys();
                foreach ((array) $value as $key => $item) {
                    if (!in_array(strtoupper((string) $key), $allowed, true) || !is_string($item) || $item === '') {
                        $fail('Template settings are KEY=value with one of: ' . implode(', ', $allowed));
                    }
                }
            }],
        ];
    }
}
