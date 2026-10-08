<?php

namespace Ulams\Reports\Http\Requests\Admin;

use Ulams\Reports\Http\Requests\ExtendableRequest;
use Ulams\Core\Enums\UserRole;

abstract class AbstractAdminOnlyRequest extends ExtendableRequest
{
    protected function passesAuthorization()
    {
        return !empty($this->user()) &&
            (method_exists($this, 'authorize') ? $this->container->call([$this, 'authorize']) : true);
    }
}
