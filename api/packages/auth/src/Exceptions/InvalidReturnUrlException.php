<?php

namespace Ulams\Auth\Exceptions;

use RuntimeException;

/** A return URL that is not on the tenant's allow-list (see {@see \Ulams\Auth\Support\ReturnUrlPolicy}). */
class InvalidReturnUrlException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The return URL is not allowed for this site.', 422);
    }
}
