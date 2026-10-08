<?php

namespace EscolaLms\Dictionaries\Http\Controllers\Swagger;

use EscolaLms\Dictionaries\Http\Requests\DictionaryAccess\ListDictionaryAccessRequest;
use EscolaLms\Dictionaries\Http\Requests\DictionaryAccess\SetDictionaryAccessRequest;
use Illuminate\Http\JsonResponse;

interface DictionaryAccessAdminApiControllerSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/dictionaries/{id}/access",
     *      summary="Get list of users with access to the dictionary",
     *      tags={"Admin Dictionaries"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Dictionary",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      ref="#/components/schemas/DictionaryAccessAdminResource"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function index(ListDictionaryAccessRequest $request): JsonResponse;

    /**
     * @OA\Post (
     *      path="/api/admin/dictionaries/{id}/access",
     *      summary="Add users with access to the dictionary",
     *      tags={"Admin Dictionaries"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Dictionary",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/SetDictionaryAccessRequest")
     *          ),
     *       ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function set(SetDictionaryAccessRequest $request): JsonResponse;
}
