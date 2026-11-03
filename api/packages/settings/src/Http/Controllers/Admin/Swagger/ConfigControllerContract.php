<?php

namespace Ulams\Settings\Http\Controllers\Admin\Swagger;

use Ulams\Settings\Http\Requests\Admin\ConfigListRequest;
use Ulams\Settings\Http\Requests\Admin\ConfigUpdateRequest;
use Illuminate\Http\JsonResponse;

interface ConfigControllerContract
{
    /**
     * @OA\Get(
     *      path="/api/admin/config", operationId="admin.config.get",
     *      summary="Get admin config",
     *      tags={"Admin Settings"},
     *      description="Get a listing of registered config keys",
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
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Schema(
     *                          @OA\Property(
     *                              property="value",
     *                              type="string"
     *                          ),
     *                          @OA\Property(
     *                              property="rules",
     *                              type="array",
     *                              @OA\Items(
     *                                  type="string"
     *                              )
     *                          ),
     *                          @OA\Property(
     *                              property="readonly",
     *                              type="boolean"
     *                          ),
     *                          @OA\Property(
     *                              property="public",
     *                              type="boolean"
     *                          ),
     *                      ),
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function list(ConfigListRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/admin/config", operationId="admin.config.post",
     *     summary="Set config for registered config keys",
     *     tags={"Admin Settings"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(
     *              property="config",
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
     *     )),
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
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Schema(
     *                          @OA\Property(
     *                              property="value",
     *                              type="string"
     *                          ),
     *                          @OA\Property(
     *                              property="rules",
     *                              type="array",
     *                              @OA\Items(
     *                                  type="string"
     *                              )
     *                          ),
     *                          @OA\Property(
     *                              property="readonly",
     *                              type="boolean"
     *                          ),
     *                          @OA\Property(
     *                              property="public",
     *                              type="boolean"
     *                          ),
     *                      ),
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function update(ConfigUpdateRequest $request): JsonResponse;
}
