<?php

namespace EscolaLms\ConsultationAccess\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TermIsBusyException extends ConsultationAccessException
{
    public function __construct(?string $message = null, int $code = Response::HTTP_BAD_REQUEST, ?Throwable $previous = null) {
        parent::__construct($message ?? __('Term is busy'), $code, $previous);
    }
}
