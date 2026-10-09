<?php

namespace Ulams\Auth\Http\Requests\Device;

use Ulams\Auth\Support\TokenScopes;

class ApproveDeviceRequestRequest extends DeviceRequestInRouteRequest
{
    protected function ability(): string
    {
        return 'approve';
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'scopes' => ['required', 'array', 'min:1', 'max:40'],
            'scopes.*' => ['required', 'string', 'in:' . implode(',', TokenScopes::all())],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:' . TokenScopes::maxDays($this->getHost())],
        ];
    }
}
