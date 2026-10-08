<?php

namespace Ulams\Settings\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\Http\Controllers\Swagger\ConfigControllerContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends UlamsBaseController implements ConfigControllerContract
{
    public function list(Request $request): JsonResponse
    {
        return $this->sendResponse(AdministrableConfig::getPublicConfig());
    }
}
