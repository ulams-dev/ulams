<?php

namespace EscolaLms\Reports\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportNotExistsException extends Exception
{
    public function __construct(string $message = null) {
        parent::__construct($message ?? __('The export for the statistics does not exist.'));
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
