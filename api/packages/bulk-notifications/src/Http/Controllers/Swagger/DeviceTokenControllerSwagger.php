<?php

namespace EscolaLms\BulkNotifications\Http\Controllers\Swagger;

use EscolaLms\BulkNotifications\Http\Requests\CreateDeviceTokenRequest;
use Illuminate\Http\JsonResponse;

interface DeviceTokenControllerSwagger
{

    /**
     * @OA\Post(
     *      path="/api/notifications/tokens",
     *      summary="Store a newly notification device token",
     *      tags={"Notification Device Tokens"},
     *      description="Store Notification Device Token",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/CreateDeviceTokenRequest")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=201,
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
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function create(CreateDeviceTokenRequest $request): JsonResponse;
}
