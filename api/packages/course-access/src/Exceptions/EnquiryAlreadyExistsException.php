<?php

namespace EscolaLms\CourseAccess\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Exception;
use Throwable;

class EnquiryAlreadyExistsException extends Exception
{
    public function __construct(?string $message = null, int $code = Response::HTTP_BAD_REQUEST, ?Throwable $previous = null) {
        parent::__construct($message ?? __('Enquiry already exists'), $code, $previous);
    }
}
