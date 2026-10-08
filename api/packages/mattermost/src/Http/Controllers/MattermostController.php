<?php

namespace EscolaLms\Mattermost\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Core\Models\User;
use EscolaLms\Mattermost\Http\Controllers\Swagger\MattermostSwagger;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MattermostController extends EscolaLmsBaseController implements MattermostSwagger
{
    private MattermostServiceContract $service;

    public function __construct(MattermostServiceContract $service)
    {
        $this->service = $service;
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        return $this->sendResponse($this->service->getUserData($user));
    }

    public function generateCredentials(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        return $this->sendResponse($this->service->generateUserCredentials($user));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        return $this->sendResponse($this->service->sendUserResetPassword($user));
    }
}
