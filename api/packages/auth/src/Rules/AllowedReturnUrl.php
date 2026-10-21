<?php

namespace Ulams\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Ulams\Auth\Support\ReturnUrlPolicy;

/**
 * A return URL that the tenant's {@see ReturnUrlPolicy} allows. Used by every request that makes the
 * API put a caller-supplied link into an e-mail (password reset, verification, account deletion,
 * imported users), so a stranger cannot point the link, and the token in it, at another site.
 */
class AllowedReturnUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!app(ReturnUrlPolicy::class)->isAllowed(is_string($value) ? $value : null)) {
            $fail('The return URL is not allowed for this site.');
        }
    }
}
