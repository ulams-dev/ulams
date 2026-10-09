<?php

namespace Ulams\Lrs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Lrs\Services\Contracts\LrsServiceContract;
use Ulams\Lrs\Services\LaunchTokenService;
use Ulams\Lrs\Http\Controllers\Swagger\LrsSwagger;
use Ulams\Core\Http\Controllers\UlamsBaseController;

class LrsController extends UlamsBaseController implements LrsSwagger
{
    private LrsServiceContract $service;

    public function __construct(LrsServiceContract $service, private readonly LaunchTokenService $launchTokens)
    {
        $this->service = $service;
    }

    public function fetch(Request $request): JsonResponse
    {
        $session = $this->launchTokens->exchange((string) $request->query('token', ''));

        if ($session === null) {
            // the error format of the cmi5 specification
            return response()->json([
                'error-code' => '1',
                'error-text' => 'The launch token is unknown or has expired.',
            ], 401);
        }

        return response()->json(['auth-token' => $session]);
    }

    public function launchParams(Request $request, int $id): JsonResponse
    {
        $params = $this->service->launchParams($id);
        $params = $this->service->saveState($params);
        $params = $this->service->saveAgent($params);

        return $this->sendResponse($params, "cmi5 Params fetched");
    }
}
