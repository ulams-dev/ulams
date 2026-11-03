<?php

namespace Ulams\Auth\Http\Controllers;

use Ulams\Auth\Events\Logout;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Http\Controllers\Swagger\LogoutSwagger;
use Ulams\Auth\Http\Requests\LogoutRequest;
use Ulams\Auth\Services\AuthService;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class LogoutApiController extends UlamsBaseController implements LogoutSwagger
{
    public function logout(LogoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->token()->revoke();
        if ($request->boolean('everywhere')) {
            // every login session of this user (one per host of the academy after a hand-off), but not the scoped tokens
            // of agents, CI or the CLI, which have their own revoke path and their own meta row
            $user->tokens()->where('name', AuthService::TOKEN_NAME)->where('revoked', false)
                ->whereNotIn('id', ApiTokenMeta::query()->select('token_id'))->update(['revoked' => true]);
        }
        event(new Logout($user));
        return $this->sendSuccess(__('You have been successfully logged out!'));
    }
}
