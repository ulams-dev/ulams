<?php

namespace Ulams\Tenancy\Exceptions;

use RuntimeException;

/** A refused entry point operation, with the HTTP status the admin API answers and the field errors. */
class EntryPointException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(string $message, public readonly int $status = 422, public readonly array $errors = [])
    {
        parent::__construct($message, $status);
    }
}
