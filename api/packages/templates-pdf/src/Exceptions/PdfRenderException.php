<?php

namespace Ulams\TemplatesPdf\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The PDF could not be rendered: the renderer service is unreachable, refused
 * the template (status and error code from the service) or the stored template
 * cannot be rendered.
 */
class PdfRenderException extends RuntimeException
{
    public function __construct(
        string $message,
        private ?int $status = null,
        private ?string $errorCode = null,
        private mixed $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getDetails(): mixed
    {
        return $this->details;
    }
}
