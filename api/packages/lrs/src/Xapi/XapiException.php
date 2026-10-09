<?php

namespace Ulams\Lrs\Xapi;

use Illuminate\Http\Response;
use RuntimeException;
use Ulams\Lrs\Enums\XApiEnum;

/**
 * An error answered to an xAPI client as a plain-text message with an HTTP status.
 */
class XapiException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = Response::HTTP_BAD_REQUEST)
    {
        parent::__construct($message);
    }

    public static function badRequest(string $message): self
    {
        return new self($message, Response::HTTP_BAD_REQUEST);
    }

    public static function unauthorized(string $message = 'Unauthorized.'): self
    {
        return new self($message, Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'Forbidden.'): self
    {
        return new self($message, Response::HTTP_FORBIDDEN);
    }

    public static function notFound(string $message = 'xAPI resource not found.'): self
    {
        return new self($message, Response::HTTP_NOT_FOUND);
    }

    public static function conflict(string $message): self
    {
        return new self($message, Response::HTTP_CONFLICT);
    }

    public static function preconditionFailed(string $message): self
    {
        return new self($message, Response::HTTP_PRECONDITION_FAILED);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * Laravel renders the exception with this response.
     */
    public function render(): Response
    {
        return new Response($this->getMessage(), $this->status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Experience-API-Version' => XApiEnum::API_VERSION,
        ]);
    }

    /**
     * Client errors are expected and not reported.
     */
    public function report(): bool
    {
        return true;
    }
}
