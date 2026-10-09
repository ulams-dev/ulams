<?php

namespace Ulams\Auth\Http\Requests\Tokens;

class RevokeTokenRequest extends TokenInRouteRequest
{
    protected function ability(): string
    {
        return 'delete';
    }
}
