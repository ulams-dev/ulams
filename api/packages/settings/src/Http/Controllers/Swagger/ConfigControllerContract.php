<?php

namespace EscolaLms\Settings\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ConfigControllerContract
{
    /**
     * @OA\Get(
     *      path="/api/config",
     *      summary="Get a listing of public config values.",
     *      tags={"Settings"},
     *      description="Get public config values",
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
     *                      type="string",
     *                  ),
     *              )
     *          )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse;
}
