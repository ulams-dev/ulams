<?php

namespace Ulams\Compliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class ComplianceController extends Controller
{
    protected function guard(Request $request, string $permission): void
    {
        abort_unless((bool) $request->user()?->can($permission, 'api'), 403);
    }

    protected function ok(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['success' => true, 'data' => $data, 'message' => 'OK'];
        if ($meta) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }
}
