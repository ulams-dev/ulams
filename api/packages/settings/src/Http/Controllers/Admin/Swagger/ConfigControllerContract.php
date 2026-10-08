<?php

namespace EscolaLms\Settings\Http\Controllers\Admin\Swagger;

use EscolaLms\Settings\Http\Requests\Admin\ConfigListRequest;
use EscolaLms\Settings\Http\Requests\Admin\ConfigUpdateRequest;
use Illuminate\Http\JsonResponse;

interface ConfigControllerContract
{
    /**
     * @OA\Get(
     *      path="/api/admin/config",
     *      summary="Get admin config",
     *      tags={"Admin Settings"},
     *      description="Get a listing of registered config keys",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
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
     *     path="/api/admin/config",
     *     summary="Set config for registered config keys",
     *     tags={"Admin Settings"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\RequestBody(
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
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
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
