<?php

namespace Ulams\Lti\Exceptions;

use RuntimeException;

/**
 * An LTI message or request failed validation. The message is safe to show; `status` is the
 * HTTP status to answer with.
 */
class LtiRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400, public readonly string $oauthError = 'invalid_request')
    {
        parent::__construct($message);
    }
}
