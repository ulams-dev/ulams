<?php

namespace Ulams\Auth\Http\Requests;

use Ulams\Auth\Services\LoginHandoffService;

/** Asks for a one-time code that hands the caller's session to another host of the academy. */
class HandoffIssueRequest extends ExtendableRequest
{
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:260'],
            'challenge' => ['required', 'string', 'regex:' . LoginHandoffService::CHALLENGE_PATTERN],
        ];
    }
}
