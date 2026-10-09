<?php

namespace Ulams\Demo\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Demo\Http\Requests\DemoLoginRequest;

interface DemoApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/demo",
     *      summary="Demo mode status and the accounts used for the automatic login",
     *      description="Exists only on a tenant with DEMO_MODE=true; 404 everywhere else.",
     *      tags={"Demo"},
     *      @OA\Response(
     *          response=200,
     *          description="Demo mode is on",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="enabled", type="boolean"),
     *                  @OA\Property(
     *                      property="users",
     *                      type="array",
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="role", type="string", enum={"student", "tutor", "admin"}),
     *                          @OA\Property(property="email", type="string")
     *                      )
     *                  ),
     *                  @OA\Property(property="front_url", type="string", nullable=true),
     *                  @OA\Property(property="admin_url", type="string", nullable=true),
     *                  @OA\Property(property="reset_cron", type="string", nullable=true)
     *              )
     *          )
     *      ),
     *      @OA\Response(response=404, description="Demo mode is off")
     * )
     */
    public function show(Request $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/demo/login",
     *      summary="Log in as the demo student or admin without a password",
     *      description="Issues a regular Passport token for the seeded account of that role, with the same response body as /api/auth/login. The demo student is given access to every published course. Exists only on a tenant with DEMO_MODE=true.",
     *      tags={"Demo"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              required={"role"},
     *              @OA\Property(property="role", type="string", enum={"student", "tutor", "admin"})
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Logged in",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="token", type="string"),
     *                  @OA\Property(property="expires_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=404, description="Demo mode is off"),
     *      @OA\Response(response=422, description="Unknown role, or no seeded account for it")
     * )
     */
    public function login(DemoLoginRequest $request): JsonResponse;
}
