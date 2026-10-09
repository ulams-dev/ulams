<?php

namespace Ulams\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Auth\Http\Controllers\Swagger\MetaSwagger;
use Ulams\Auth\Support\TokenScopes;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Http\Controllers\UlamsBaseController;

/** `GET /api/meta`: what this host can do, so clients (the CLI, MCP, the SDK) can adapt without trial and error. */
class MetaController extends UlamsBaseController implements MetaSwagger
{
    public const CONTRACT = 1;

    public function show(Request $request): JsonResponse
    {
        $ai = interface_exists(LlmClient::class) && app()->bound(LlmClient::class) && app(LlmClient::class)->enabled();

        return $this->sendResponse([
            'api' => 'ulams',
            'version' => (string) config(UlamsAuthServiceProvider::CONFIG_KEY . '.version', 'dev'),
            'contract' => self::CONTRACT,
            'host' => $request->getHost(),
            'kind' => TokenScopes::isPlatformHost($request->getHost()) ? 'platform' : 'tenant',
            'features' => [
                'ai' => $ai,
                'courseBuilder' => $ai && class_exists(\Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider::class),
                'livingCourse' => class_exists(\Ulams\LivingCourse\UlamsLivingCourseServiceProvider::class),
                'deviceLogin' => Route::has('auth.device.code'),
                'scopedTokens' => true,
                'idempotency' => true,
                'platformApi' => (bool) config('ulams_tenancy.platform_api', false) && TokenScopes::isPlatformHost($request->getHost()),
                'demo' => (bool) config('ulams_demo.enabled', false),
            ],
        ], __('Capabilities'));
    }
}
