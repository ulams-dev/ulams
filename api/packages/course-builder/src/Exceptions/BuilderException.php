<?php

namespace Ulams\CourseBuilder\Exceptions;

use RuntimeException;

/** A builder error with an HTTP status and a message safe to show the author. */
class BuilderException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
