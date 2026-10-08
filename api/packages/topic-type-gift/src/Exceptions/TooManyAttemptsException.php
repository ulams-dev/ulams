<?php

namespace EscolaLms\TopicTypeGift\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TooManyAttemptsException extends Exception
{
    public function __construct(int $code = Response::HTTP_BAD_REQUEST, ?Throwable $previous = null) {
        parent::__construct($message ?? __('Too many attempts'), $code, $previous);
    }
}
