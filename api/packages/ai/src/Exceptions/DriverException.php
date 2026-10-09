<?php

namespace Ulams\Ai\Exceptions;

use RuntimeException;

class DriverException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $missingCassette = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
