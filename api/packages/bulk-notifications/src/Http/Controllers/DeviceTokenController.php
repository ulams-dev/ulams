<?php

namespace EscolaLms\BulkNotifications\Http\Controllers;

use EscolaLms\BulkNotifications\Http\Controllers\Swagger\DeviceTokenControllerSwagger;
use EscolaLms\BulkNotifications\Http\Requests\CreateDeviceTokenRequest;
use EscolaLms\BulkNotifications\Services\Contracts\DeviceTokenServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends EscolaLmsBaseController implements DeviceTokenControllerSwagger
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
