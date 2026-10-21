<?php

namespace Ulams\CourseBuilder\CourseAsCode;

use RuntimeException;

/** A course-as-code request the server refuses, with a stable `code` for the CLI and extra JSON for the client. */
final class CourseAsCodeException extends RuntimeException
{
    /** @param array<string,mixed> $data */
    public function __construct(string $message, public readonly int $status, public readonly string $errorCode, public readonly array $data = [])
    {
        parent::__construct($message);
    }
}
