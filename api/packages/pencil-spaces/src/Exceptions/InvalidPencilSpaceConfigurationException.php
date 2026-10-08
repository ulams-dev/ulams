<?php

namespace EscolaLms\PencilSpaces\Exceptions;

use Exception;

class InvalidPencilSpaceConfigurationException extends Exception
{
    public function __construct(string $message = null) {
        parent::__construct($message ?? __('Pencil Spaces configuration is invalid'));
    }
}
