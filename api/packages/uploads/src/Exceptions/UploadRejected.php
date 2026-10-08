<?php

namespace Ulams\Uploads\Exceptions;

use RuntimeException;

/**
 * An upload or archive failed the upload guard. `reason` is a stable machine code
 * (e.g. `zip_slip`, `zip_bomb`, `too_large`); the message is safe to show to the author.
 */
class UploadRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
