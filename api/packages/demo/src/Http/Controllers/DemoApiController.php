<?php

namespace Ulams\Demo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Auth\Http\Resources\LoginResource;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Demo\Exceptions\DemoUserNotFoundException;
use Ulams\Demo\Http\Controllers\Swagger\DemoApiSwagger;
use Ulams\Demo\Http\Requests\DemoLoginRequest;
use Ulams\Demo\Services\Contracts\DemoServiceContract;
use Ulams\Demo\UlamsDemoServiceProvider;

class DemoApiController extends UlamsBaseController implements DemoApiSwagger
{
    public function __construct(private DemoServiceContract $demo)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $config = UlamsDemoServiceProvider::CONFIG_KEY;

        return $this->sendResponse([
            'enabled' => true,
            'users' => $this->demo->accounts(),
            'front_url' => config($config . '.front_url'),
            'admin_url' => config($config . '.admin_url'),
            'reset_cron' => config($config . '.reset.schedule') ? config($config . '.reset.cron') : null,
        ], 'Demo mode');
    }

    public function login(DemoLoginRequest $request): JsonResponse
    {
        try {
            $token = $this->demo->login($request->demoRole());
        } catch (DemoUserNotFoundException $exception) {
            return $this->sendError($exception->getMessage(), 422);
        }

        // Same body as POST /api/auth/login, so the front and the admin store it as usual.
        return $this->sendResponseForResource(LoginResource::make($token), __('Login successful'));
    }
}
