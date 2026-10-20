<?php

namespace Ulams\Auth\Http\Requests\Tokens;

class TokenUsageRequest extends TokenInRouteRequest
{
    protected function ability(): string
    {
        return 'viewUsage';
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['days' => ['nullable', 'integer', 'min:1', 'max:90']];
    }
}
