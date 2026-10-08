<?php

namespace EscolaLms\TopicTypeGift\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UnknownGiftTypeException extends Exception
{
    public function __construct(?string $type = null, int $code = Response::HTTP_BAD_REQUEST, ?Throwable $previous = null) {
        parent::__construct($message ?? __('Unknown GIFT question type :type', ['type' => $type]), $code, $previous);
    }
}
