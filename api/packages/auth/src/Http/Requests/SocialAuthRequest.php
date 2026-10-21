<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Services\LoginHandoffService;
use Ulams\Auth\Support\ReturnUrlPolicy;

/** Start of a social login: where to come back to and the front end's PKCE challenge. */
class SocialAuthRequest extends ExtendableRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['provider' => $this->route('provider')]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:facebook,google'],
            'return_url' => [
                'sometimes', 'nullable', 'string', 'max:2048',
                fn (string $attribute, mixed $value, \Closure $fail) => app(ReturnUrlPolicy::class)->isAllowed($value)
                    ?: $fail('The return URL is not allowed for this site.'),
            ],
            'challenge' => ['required', 'string', 'regex:' . LoginHandoffService::CHALLENGE_PATTERN],
        ];
    }
}
