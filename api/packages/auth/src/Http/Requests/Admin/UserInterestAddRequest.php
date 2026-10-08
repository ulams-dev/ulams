<?php

namespace Ulams\Auth\Http\Requests\Admin;

use Ulams\Categories\Models\Category;

class UserInterestAddRequest extends AbstractUserIdInRouteRequest
{
    public function authorize()
    {
        return $this->user()->can('updateInterests', $this->getRouteUser());
    }

    public function rules()
    {
        $rules = [
            'interest_id' => [
                'required',
                'exists:' . Category::query()->getQuery()->from . ',id'
            ]
        ];
        return array_merge(parent::rules(), $rules);
    }
}
