<?php

namespace EscolaLms\Settings\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Settings\Http\Controllers\Swagger\ConfigControllerContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends EscolaLmsBaseController implements ConfigControllerContract
{
    public function list(Request $request): JsonResponse
    {
        return $this->sendResponse(AdministrableConfig::getPublicConfig());
    }
}
