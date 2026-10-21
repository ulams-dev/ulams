<?php

namespace Ulams\Settings\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ConfigControllerContract
{
    /**
     * @OA\Get(
     *      path="/api/config", operationId="config.get",
     *      summary="Get a listing of public config values.",
     *      tags={"Settings"},
     *      description="Get public config values",
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
     *                      type="string",
     *                  ),
     *              )
     *          )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse;
}
