<?php

namespace Ulams\Auth\Http\Requests\Device;

class DenyDeviceRequestRequest extends DeviceRequestInRouteRequest
{
    protected function ability(): string
    {
        return 'deny';
    }
}
