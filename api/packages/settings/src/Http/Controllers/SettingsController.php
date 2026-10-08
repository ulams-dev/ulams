<?php

namespace EscolaLms\Settings\Http\Controllers;

use EscolaLms\Settings\Http\Controllers\Swagger\SettingsControllerContract;
use EscolaLms\Settings\Services\Contracts\SettingsServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use EscolaLms\Settings\Http\Resources\SettingResource;
use EscolaLms\Settings\Http\Resources\SettingsCollection;

class SettingsController extends EscolaLmsBaseController implements SettingsControllerContract
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
