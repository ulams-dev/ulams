<?php

namespace EscolaLms\Notifications\Http\Controllers\Swagger;

use EscolaLms\Notifications\Http\Requests\NotificationEventsRequest;
use EscolaLms\Notifications\Http\Requests\NotificationReadAllRequest;
use EscolaLms\Notifications\Http\Requests\NotificationReadRequest;
use EscolaLms\Notifications\Http\Requests\NotificationsRequest;
use EscolaLms\Notifications\Http\Requests\NotificationsUserRequest;
use Illuminate\Http\JsonResponse;

interface NotificationsApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/notifications/all",
     *      summary="Get notifications",
     *      tags={"Notifications Admin"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Get paginated list of notifications sent using `database` channel",
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
     *          name="event",
     *          description="Event class filter",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="include_read",
     *          description="Include read notifications",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="date_from",
     *          description="From date filter",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="date",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="date_to",
     *          description="To date filter",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="date",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="user",
     *          description="User Id (if empty, will return all notifications)",
     *          required=false,
     *          in="path",
     *          @OA\Schema(type="integer"),
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
     *                  @OA\Items(ref="#/components/schemas/Notification")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    public function index(NotificationsRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/notifications",
     *      summary="Get list of events for which notifications exist",
     *      tags={"Notifications"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Get list of events for which notifications exist",
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
     *          name="event",
     *          description="Event class filter",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="include_read",
     *          description="Include read notifications",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="date_from",
     *          description="From date filter",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="date",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="date_to",
     *          description="To date filter",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="date",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="order_by",
     *          description="Order by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              enum={"created_at", "notifiable_id", "event"},
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="order",
     *          description="Order",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              enum={"ASC", "DESC"},
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
     *                  @OA\Items(@OA\Schema(type="string"))
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    /**
     * @OA\Get(
     *      path="/api/admin/notifications",
     *      summary="Get list of events for which notifications exist",
     *      tags={"Notifications Admin"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Get list of events for which notifications exist",
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
     *                  @OA\Items(@OA\Schema(type="string"))
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    public function user(NotificationsUserRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/notifications/events",
     *      summary="Get list of events for which notifications exist",
     *      tags={"Notifications Admin"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Get list of events for which notifications exist",
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
     *                  @OA\Items(@OA\Schema(type="string"))
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function events(NotificationEventsRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/notifications/{notification}/read",
     *      summary="Mark notification as read",
     *      tags={"Notifications Admin"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Mark notification as read",
     *      @OA\Parameter(
     *          name="notification",
     *          description="Notification uuid / id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="string"),
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
     *                  type="object",
     *                  @OA\Schema(ref="#/components/schemas/Notification"),
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    /**
     * @OA\Post(
     *      path="/api/notifications/{notification}/read",
     *      summary="Mark notification as read",
     *      tags={"Notifications"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Mark notification as read",
     *      @OA\Parameter(
     *          name="notification",
     *          description="Notification uuid / id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="string"),
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
     *                  type="object",
     *                  @OA\Schema(ref="#/components/schemas/Notification"),
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function read(NotificationReadRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/notifications/read-all",
     *      summary="Mark all notifications as read",
     *      tags={"Notifications"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Mark all notifications as read",
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
     *              )
     *          )
     *      )
     * )
     */

    /**
     * @OA\Post(
     *      path="/api/admin/notifications/read-all",
     *      summary="Mark all notifications as read",
     *      tags={"Notifications Admin"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="Mark all notifications as read",
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
     *              )
     *          )
     *      )
     * )
     */
    public function readAll(NotificationReadAllRequest $request): JsonResponse;
}
