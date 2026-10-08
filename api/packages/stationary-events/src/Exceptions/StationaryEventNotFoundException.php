<?php
namespace EscolaLms\StationaryEvents\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class StationaryEventNotFoundException extends UnprocessableEntityHttpException
{
    public function __construct(?string $message = 'Stationary Event Not Found', int $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous, $code);
    }
}
