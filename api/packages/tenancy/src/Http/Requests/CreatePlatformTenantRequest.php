<?php

namespace Ulams\Tenancy\Http\Requests;

use Closure;
use InvalidArgumentException;
use Ulams\Tenancy\Support\TenantNaming;

class CreatePlatformTenantRequest extends PlatformRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                try {
                    TenantNaming::assertValidSlug((string) $value);
                } catch (InvalidArgumentException $e) {
                    $fail($e->getMessage());
                }
            }],
            'name' => ['nullable', 'string', 'max:100', 'regex:/^[^<>]*$/'],
            'theme' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{1,40}$/'],
            'accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'users' => ['nullable', 'integer', 'min:0', 'max:50'],
            'demo' => ['nullable', 'boolean'],
        ];
    }
}
