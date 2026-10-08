<?php

namespace EscolaLms\BulkNotifications\Exceptions;

use Exception;

class UnsupportedNotification extends Exception
{
    public function __construct(string $message = null) {
        parent::__construct($message ?? __('Unsupported notification type.'));
    }
}



