<?php

namespace Ulams\Core\Http\Controllers;

use Ulams\Core\Http\Controllers\Swagger\HealthCheckSwagger;
use Ulams\Core\Services\Contracts\HealthCheckServiceContract;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends UlamsBaseController implements HealthCheckSwagger
{
    public function __construct(private HealthCheckServiceContract $healthCheckService)
    {
    }

    public function healthCheck(): JsonResponse
    {
        return $this->sendResponse($this->healthCheckService->getHealthData());
    }
}
