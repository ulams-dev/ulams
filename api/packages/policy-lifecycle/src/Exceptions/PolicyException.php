<?php

namespace Ulams\PolicyLifecycle\Exceptions;

use RuntimeException;

/** A policy action that cannot be done in the current state; the message is shown to the owner. */
final class PolicyException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
