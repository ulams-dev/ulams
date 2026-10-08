<?php

namespace EscolaLms\Categories\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CategoryIsUsed extends Exception
{
    public function __construct(string $message = null) {
        parent::__construct($message ?? __('Category is used'));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'success' => false,
        ],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
