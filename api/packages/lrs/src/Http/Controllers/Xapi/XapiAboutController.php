<?php

namespace Ulams\Lrs\Http\Controllers\Xapi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Ulams\Lrs\Enums\XApiEnum;

/**
 * xAPI About resource.
 */
class XapiAboutController extends Controller
{
    public function get(): JsonResponse
    {
        return new JsonResponse(['version' => [XApiEnum::API_VERSION]], 200, [
            'X-Experience-API-Version' => XApiEnum::API_VERSION,
        ]);
    }
}
