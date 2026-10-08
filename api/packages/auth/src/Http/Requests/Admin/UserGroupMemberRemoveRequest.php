<?php

namespace Ulams\Auth\Http\Requests\Admin;

use Ulams\Auth\Models\User;

class UserGroupMemberRemoveRequest extends AbstractGroupIdInRouteRequest
{
    public function authorize()
    {
        return $this->user()->can('removeMember', $this->getGroupFromRoute());
    }

    protected function prepareForValidation()
    {
        parent::prepareForValidation();
        $this->merge(['user_id' => $this->route('user_id')]);
    }

    public function getUserFromRoute(): User
    {
        return User::query()->findOrFail($this->route('user_id'));
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            'user_id' => [
                'required',
                'exists:' . User::query()->getQuery()->from . ',id'
            ]
        ]);
    }
}
