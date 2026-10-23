<?php

namespace Ulams\Auth\Http\Requests;

class LogoutRequest extends ExtendableRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !empty($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // sign out of every host of the academy (ADR 0195): all the user's login sessions, not scoped tokens
            'everywhere' => ['sometimes', 'boolean'],
        ];
    }
}
