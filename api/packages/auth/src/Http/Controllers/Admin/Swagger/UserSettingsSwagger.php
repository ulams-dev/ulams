<?php

namespace Ulams\Auth\Http\Controllers\Admin\Swagger;

use Ulams\Auth\Http\Requests\Admin\UserSettingsListRequest;
use Ulams\Auth\Http\Requests\Admin\UserSettingsUpdateRequest;
use Illuminate\Http\JsonResponse;

interface UserSettingsSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}/settings",
     *     summary="Get user settings",
     *     description="",
     *     tags={"Admin User Settings"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *              format="int64",
     *          ),
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation, returns list of user settings",
     *          @OA\JsonContent(
     *                  type="array",
     *                  @OA\Items(
     *                      type="object"
     *                  )
     *          )
     *     ),
     * )
     */
    public function listUserSettings(UserSettingsListRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *     path="/api/admin/users/{id}/settings",
     *     summary="Update user setting(s) without changing other settings",
     *     tags={"Admin User Settings"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *              format="int64",
     *          ),
     *     ),
     *     @OA\RequestBody(
     *         @OA\Property(
     *              property="settings",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Schema(
     *                      @OA\Property(
     *                          property="key",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="value",
     *                          type="string"
     *                      ),
     *                  )
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          )
     *     )
     * )
     */
    public function patchUserSettings(UserSettingsUpdateRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *     path="/api/admin/users/{id}/settings",
     *     summary="Set user setting (removes settings not sent)",
     *     tags={"Admin User Settings"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *              format="int64",
     *          ),
     *     ),
     *     @OA\RequestBody(
     *         @OA\Property(
     *              property="settings",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Schema(
     *                      @OA\Property(
     *                          property="key",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="value",
     *                          type="string"
     *                      ),
     *                  )
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          )
     *     )
     * )
     */
    public function putUserSettings(UserSettingsUpdateRequest $request): JsonResponse;
}
