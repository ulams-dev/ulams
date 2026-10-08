<?php

namespace Ulams\Auth\Http\Controllers;

use Ulams\Auth\Events\Logout;
use Ulams\Auth\Http\Controllers\Swagger\LogoutSwagger;
use Ulams\Auth\Http\Requests\LogoutRequest;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class LogoutApiController extends UlamsBaseController implements LogoutSwagger
{
    public function logout(LogoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->token()->revoke();
        event(new Logout($user));
        return $this->sendSuccess(__('You have been successfully logged out!'));
    }
}
