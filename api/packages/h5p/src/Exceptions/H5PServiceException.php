<?php

namespace Ulams\H5P\Exceptions;

use RuntimeException;
use Throwable;

class H5PServiceException extends RuntimeException
{
    private ?int $status;

    public function __construct(string $message, ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $status ?? 0, $previous);
        $this->status = $status;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }
}
