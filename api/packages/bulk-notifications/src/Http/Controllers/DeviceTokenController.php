<?php

namespace Ulams\BulkNotifications\Http\Controllers;

use Ulams\BulkNotifications\Http\Controllers\Swagger\DeviceTokenControllerSwagger;
use Ulams\BulkNotifications\Http\Requests\CreateDeviceTokenRequest;
use Ulams\BulkNotifications\Services\Contracts\DeviceTokenServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends UlamsBaseController implements DeviceTokenControllerSwagger
{
    public function __construct(private DeviceTokenServiceContract $deviceTokenService)
    {
    }

    public function create(CreateDeviceTokenRequest $request): JsonResponse
    {
        $this->deviceTokenService->create($request->toDto());

        return $this->sendSuccess('Device token created successfully.');
    }
}
