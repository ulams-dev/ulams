<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Services\LoginHandoffService;

/** Redemption of a one-time login code together with the verifier of the browser that started the login. */
class SocialExchangeRequest extends ExtendableRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'verifier' => ['required', 'string', 'regex:' . LoginHandoffService::VERIFIER_PATTERN],
        ];
    }
}
