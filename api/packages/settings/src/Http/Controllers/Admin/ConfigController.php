<?php

namespace Ulams\Settings\Http\Controllers\Admin;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Settings\Events\SettingPackageConfigUpdated;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\Http\Controllers\Admin\Swagger\ConfigControllerContract;
use Ulams\Settings\Http\Requests\Admin\ConfigListRequest;
use Ulams\Settings\Http\Requests\Admin\ConfigUpdateRequest;
use Illuminate\Http\JsonResponse;

class ConfigController extends UlamsBaseController implements ConfigControllerContract
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
