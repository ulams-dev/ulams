<?php

namespace Ulams\Auth\Http\Requests\Tokens;

class TokenAuditRequest extends TokenInRouteRequest
{
    protected function ability(): string
    {
        return 'viewAudit';
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['per_page' => ['nullable', 'integer', 'min:1', 'max:200'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
