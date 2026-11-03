<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Rules\AllowedReturnUrl;

class CompleteSocialDataRequest extends ExtendableRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['token' => $this->route('token')]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'token' => ['required', 'string', 'max:255', 'exists:pre_users,token'],
            'return_url' => ['sometimes', 'nullable', 'string', 'max:2048', new AllowedReturnUrl()],
        ];
    }
}
