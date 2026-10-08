<?php

namespace EscolaLms\BulkNotifications\Http\Controllers\Swagger;

use EscolaLms\BulkNotifications\Http\Requests\ListBulkNotificationRequest;
use EscolaLms\BulkNotifications\Http\Requests\SendUserBulkNotificationRequest;
use EscolaLms\BulkNotifications\Http\Requests\SendMulticastBulkNotificationRequest;
use Illuminate\Http\JsonResponse;

interface BulkNotificationControllerSwagger
{

    /**
     * @OA\Post(
     *      path="/api/admin/bulk-notifications/send",
     *      summary="Store a newly notification",
     *      tags={"Admin Bulk Notifications"},
     *      description="Store Bulk Notification",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/SendUserBulkNotificationRequest")
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
     *                      property="data",
     *                      ref="#/components/schemas/BulkNotificationResource"
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
    public function send(SendUserBulkNotificationRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/bulk-notifications/send/multicast",
     *      summary="Store a newly notification",
     *      tags={"Admin Bulk Notifications"},
     *      description="Store Bulk Notification",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/SendMulticastBulkNotificationRequest")
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
     *                      property="data",
     *                      ref="#/components/schemas/BulkNotificationResource"
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
    public function sendMulticast(SendMulticastBulkNotificationRequest $request): JsonResponse;


    /**
     * @OA\Get(
     *      path="/api/admin/bulk-notifications",
     *      summary="Get a listing of the bulk notifications",
     *      tags={"Admin Bulk Notifications"},
     *      description="Get all Bulk Notifications",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="page",
     *          description="Pagination Page Number",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=1,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Pagination Per Page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=15,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="channel",
     *          description="Bulk notification channel [EscolaLms\BulkNotifications\Channels\PushNotificationChannel]",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
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
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/BulkNotificationResource")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    public function list(ListBulkNotificationRequest $request): JsonResponse;
}
