<?php

namespace Ulams\Auth\Exceptions;

use RuntimeException;

/** A login state or one-time code that cannot be used. The reason is for logs and tests, never for the browser. */
class LoginHandoffException extends RuntimeException
{
    public const MALFORMED = 'malformed';
    public const BAD_SIGNATURE = 'bad_signature';
    public const EXPIRED = 'expired';
    public const REPLAYED = 'replayed';
    public const UNKNOWN = 'unknown';
    public const WRONG_PROVIDER = 'wrong_provider';
    public const WRONG_HOST = 'wrong_host';
    public const BAD_VERIFIER = 'bad_verifier';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('Login hand-off refused: ' . $reason, 400);
    }
}
