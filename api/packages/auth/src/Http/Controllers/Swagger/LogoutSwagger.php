<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

use Ulams\Auth\Http\Requests\LogoutRequest;
use Illuminate\Http\JsonResponse;

interface LogoutSwagger
{
    /**
     * @OA\Post(
     *      path="/api/auth/logout", operationId="auth.logout.post",
     *      description="User logout. With `everywhere` true every login session of the user is revoked too (one per host of the academy after a hand-off); scoped tokens of agents, CI and the CLI are not.",
     *      tags={"Auth"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="everywhere", type="boolean"))),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")
     *      )
     *   )
     */
    public function logout(LogoutRequest $request): JsonResponse;
}
