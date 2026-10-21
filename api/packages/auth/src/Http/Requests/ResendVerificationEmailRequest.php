<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Rules\AllowedReturnUrl;
use Ulams\Auth\UlamsAuthServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class ResendVerificationEmailRequest extends ExtendableRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'return_url' => ['nullable', 'url', 'max:2048', new AllowedReturnUrl(), Rule::requiredIf(fn () => !Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.return_url'))],
        ];
    }
}
