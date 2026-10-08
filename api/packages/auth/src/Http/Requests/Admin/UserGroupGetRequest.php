<?php

namespace Ulams\Auth\Http\Requests\Admin;

class UserGroupGetRequest extends AbstractGroupIdInRouteRequest
{
    public function authorize()
    {
        return $this->user()->can('view', $this->getGroupFromRoute());
    }
}
