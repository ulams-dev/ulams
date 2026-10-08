<?php

namespace EscolaLms\Reports\Http\Requests\Admin;

use EscolaLms\Reports\Http\Requests\ExtendableRequest;
use EscolaLms\Core\Enums\UserRole;

abstract class AbstractAdminOnlyRequest extends ExtendableRequest
{
    protected function passesAuthorization()
    {
        return !empty($this->user()) &&
            (method_exists($this, 'authorize') ? $this->container->call([$this, 'authorize']) : true);
    }
}
