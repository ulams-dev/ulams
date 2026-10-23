<?php

namespace Ulams\CommerceSylius\Http;

use RuntimeException;

/** Sylius did not do what was asked (or could not be reached). Never carries credentials or tokens. */
class SyliusException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message, $status);
    }
}
