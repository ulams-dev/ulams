<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Rules\AllowedReturnUrl;

class ForgotPasswordRequest extends ExtendableRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return empty($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => ['required', 'email'],
            'return_url' => ['required', 'string', 'max:2048', new AllowedReturnUrl()],
        ];
    }
}
