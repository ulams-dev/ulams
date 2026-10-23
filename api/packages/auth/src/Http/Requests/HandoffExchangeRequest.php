<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Services\LoginHandoffService;

/** Redemption of a hand-off code by the target host, with the verifier of the browser that asked for it. */
class HandoffExchangeRequest extends ExtendableRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'verifier' => ['required', 'string', 'regex:' . LoginHandoffService::VERIFIER_PATTERN],
            'host' => ['required', 'string', 'max:260'],
        ];
    }
}
