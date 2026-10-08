<?php

namespace EscolaLms\Payments\Exceptions;

class ActionNotSupported extends PaymentException
{
    public function __construct()
    {
        parent::__construct(__('Gateway not supported this action'));
    }
}
