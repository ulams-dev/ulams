<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Models\User;

class ImpersonateRequest extends ExtendableRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('impersonate', User::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
