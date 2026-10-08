<?php

namespace Ulams\Auth\Http\Requests\Admin;

use Ulams\Auth\Http\Requests\ExtendableRequest;
use Ulams\Auth\Models\Group;

class UserGroupListRequest extends ExtendableRequest
{
    public function authorize()
    {
        return $this->user()->can('viewAny', Group::class);
    }

    public function rules()
    {
        return [
            'search' => ['sometimes', 'string'],
            'parent_id' => ['sometimes', 'integer'],
            'order_by' => ['sometimes', 'string', 'in:id,name,registerable,parent_name,created_at'],
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
        ];
    }
}
