<?php

namespace Ulams\Tutor\Services;

use RuntimeException;

/** A reason the tutor did not answer; `status` is the HTTP status the API uses. */
final class TutorException extends RuntimeException
{
    public const DISABLED = 'disabled';
    public const FORBIDDEN = 'forbidden';
    public const LIMIT = 'limit';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly string $reason, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}
