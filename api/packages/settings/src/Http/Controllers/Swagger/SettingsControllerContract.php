<?php

namespace Ulams\Settings\Http\Controllers\Swagger;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

interface SettingsControllerContract
{

    /**
     * @OA\Get(
     *      path="/api/settings", operationId="settings.get",
     *      summary="Get a listing of the public and enuerable settings.",
     *      tags={"Settings"},
     *      description="Get settings",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    public function index(Request $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/settings/{group}/{key}", operationId="settings.by-group.by-key.get",
     *      summary="Display the specified public setting",
     *      tags={"Settings"},
     *      description="Get Course",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="group",
     *          description="group of setting",
     *          @OA\Schema(
     *             type="string",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Parameter(
     *          name="key",
     *          description="key of setting",
     *          @OA\Schema(
     *             type="string",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    public function show(string $group, string $key, Request $request): JsonResponse;
}
