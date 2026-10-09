<?php

namespace Ulams\Jitsi\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class RecordingUrlNotAllowedException extends Exception
{
    public function __construct($message = '', $code = 0, ?\Throwable $previous = null)
    {
        $message = $message ?: __('Recording URL is not allowed');
        $code = $code ?: 422;
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
