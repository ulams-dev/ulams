<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

use Ulams\Auth\Http\Requests\LogoutRequest;
use Illuminate\Http\JsonResponse;

interface LogoutSwagger
{
    /**
     * @OA\Post(
     *      path="/api/auth/logout", operationId="auth.logout.post",
     *      description="User logout",
     *      tags={"Auth"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")
     *      )
     *   )
     */
    public function logout(LogoutRequest $request): JsonResponse;
}
