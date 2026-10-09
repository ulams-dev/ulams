<?php

namespace Ulams\Auth\Http\Requests\Device;

class ShowDeviceRequestRequest extends DeviceRequestInRouteRequest
{
    protected function ability(): string
    {
        return 'view';
    }
}
