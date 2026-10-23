<?php

namespace Ulams\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;

class LivenessController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'up'])->header('Cache-Control', 'no-store');
    }
}
