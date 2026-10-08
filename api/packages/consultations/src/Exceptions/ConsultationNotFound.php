<?php

namespace EscolaLms\Consultations\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class ConsultationNotFound extends UnprocessableEntityHttpException
{
    public function __construct($message = "Consultation Not Found", $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous, $code);
    }
}
