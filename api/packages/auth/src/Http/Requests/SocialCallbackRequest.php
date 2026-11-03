<?php

namespace Ulams\Auth\Http\Requests;

/** The provider's redirect back to us: the signed state is checked by the service, not here. */
class SocialCallbackRequest extends ExtendableRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['provider' => $this->route('provider')]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:facebook,google'],
            'state' => ['nullable', 'string', 'max:256'],
            'error' => ['nullable', 'string', 'max:255'],
        ];
    }
}
