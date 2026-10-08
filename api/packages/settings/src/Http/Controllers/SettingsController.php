<?php

namespace Ulams\Settings\Http\Controllers;

use Ulams\Settings\Http\Controllers\Swagger\SettingsControllerContract;
use Ulams\Settings\Services\Contracts\SettingsServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Ulams\Settings\Http\Resources\SettingResource;
use Ulams\Settings\Http\Resources\SettingsCollection;

class SettingsController extends UlamsBaseController implements SettingsControllerContract
{
    private SettingsServiceContract $service;

    public function __construct(SettingsServiceContract $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $settings = $this->service->publicList();
        return $this->sendResponse(new SettingsCollection($settings), "index success");
    }

    public function show(string $group, string $key, Request $request): JsonResponse
    {
        $setting = $this->service->find($group, $key, true);
        return $this->sendResponse(new SettingResource($setting), "show success");
    }
}
