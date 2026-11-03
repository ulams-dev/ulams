<?php

namespace Ulams\Identity\Exceptions;

use RuntimeException;

/**
 * A sign-in the provider authenticated but this academy does not accept. `reason` is the coarse code the browser
 * is sent back with (the front end maps it to a sentence); `detail` is the precise code for the audit log.
 */
class SsoRefusedException extends RuntimeException
{
    public const LINK_REQUIRED = 'link_required';
    public const EMAIL_REQUIRED = 'email_required';
    public const EMAIL_UNVERIFIED = 'email_unverified';
    public const ACCOUNT_UNAVAILABLE = 'account_unavailable';
    public const SIGNUP_DISABLED = 'signup_disabled';

    public function __construct(public readonly string $reason, public readonly string $detail = '')
    {
        parent::__construct('Sign-in refused: ' . $reason);
    }
}
