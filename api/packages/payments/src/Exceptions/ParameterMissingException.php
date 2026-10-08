<?php

namespace EscolaLms\Payments\Exceptions;

class ParameterMissingException extends PaymentException
{
    public function __construct(array $missing = [])
    {
        parent::__construct(__('Missing Payment Gateway parameters: :parameters', ['parameters' => implode(', ', $missing)]));
    }
}
