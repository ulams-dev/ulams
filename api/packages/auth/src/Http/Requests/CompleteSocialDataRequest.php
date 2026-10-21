<?php

namespace Ulams\Auth\Http\Requests;

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
            'return_url' => [
                'sometimes', 'nullable', 'string', 'max:2048',
                fn (string $attribute, mixed $value, \Closure $fail) => app(\Ulams\Auth\Support\ReturnUrlPolicy::class)->isAllowed($value)
                    ?: $fail('The return URL is not allowed for this site.'),
            ],
        ];
    }
}
