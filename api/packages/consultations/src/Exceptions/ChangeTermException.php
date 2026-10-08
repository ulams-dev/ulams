<?php

namespace EscolaLms\Consultations\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

class ChangeTermException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = $message ?: __('Term is not changed');
        $code = $code ?: 400;
        parent::__construct($message, $code, $previous);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'data' => [
                'code' => $this->getCode(),
                'message' => $this->getMessage()
            ]
        ], $this->getCode());
    }
}


