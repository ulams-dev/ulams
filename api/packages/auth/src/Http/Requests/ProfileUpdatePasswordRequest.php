<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Models\User;
use Ulams\Auth\Rules\MatchOldPassword;

class ProfileUpdatePasswordRequest extends ExtendableRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('update', $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'current_password' => ['required', new MatchOldPassword],
            'new_password' => User::PASSWORD_RULES,
            'new_confirm_password' => ['same:new_password'],
        ];
    }
}
