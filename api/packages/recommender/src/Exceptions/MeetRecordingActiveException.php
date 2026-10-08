<?php

namespace EscolaLms\Recommender\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MeetRecordingActiveException extends Exception
{
    public function __construct(string $message = null)
    {
        parent::__construct($message ?? __('Active recording found for this term'), 422);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
