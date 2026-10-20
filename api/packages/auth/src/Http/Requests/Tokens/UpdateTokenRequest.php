<?php

namespace Ulams\Auth\Http\Requests\Tokens;

/** Only the rate limit can change after a token is issued; scopes and expiry are fixed at creation. */
class UpdateTokenRequest extends TokenInRouteRequest
{
    protected function ability(): string
    {
        return 'update';
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['rate_limit_per_minute' => ['present', 'nullable', 'integer', 'min:1', 'max:6000']];
    }
}
