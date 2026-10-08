<?php

namespace EscolaLms\Settings\Http\Controllers\Admin;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Settings\Events\SettingPackageConfigUpdated;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Settings\Http\Controllers\Admin\Swagger\ConfigControllerContract;
use EscolaLms\Settings\Http\Requests\Admin\ConfigListRequest;
use EscolaLms\Settings\Http\Requests\Admin\ConfigUpdateRequest;
use Illuminate\Http\JsonResponse;

class ConfigController extends EscolaLmsBaseController implements ConfigControllerContract
{
    public function list(ConfigListRequest $request): JsonResponse
    {
        return $this->sendResponse(AdministrableConfig::getConfig());
    }

    public function update(ConfigUpdateRequest $request): JsonResponse
    {
        AdministrableConfig::setConfig($request->input('config'));
        AdministrableConfig::storeConfig();
        event(new SettingPackageConfigUpdated($request->user(), AdministrableConfig::getConfig()));
        return $this->sendResponse(AdministrableConfig::getConfig());
    }
}
