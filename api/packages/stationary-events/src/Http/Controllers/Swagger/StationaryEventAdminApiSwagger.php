<?php

namespace EscolaLms\StationaryEvents\Http\Controllers\Swagger;

use EscolaLms\StationaryEvents\Http\Requests\CreateStationaryEventRequest;
use EscolaLms\StationaryEvents\Http\Requests\DeleteStationaryEventRequest;
use EscolaLms\StationaryEvents\Http\Requests\ListStationaryEventRequest;
use EscolaLms\StationaryEvents\Http\Requests\ReadStationaryEventRequest;
use EscolaLms\StationaryEvents\Http\Requests\UpdateStationaryEventRequest;
use Illuminate\Http\JsonResponse;

interface StationaryEventAdminApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/stationary-events",
     *      summary="Get a listing of the Stationary events",
     *      tags={"Admin Stationary Events"},
     *      description="Get all Stationary Events",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"finished_at", "started_at", "created_at", "name"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="order",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"ASC", "DESC"}
     *          ),
     *      ),
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
     *          name="name",
     *          description="Stationary event name %LIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"draft", "archived", "published", "published_unactivated"}
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
     *                  @OA\Items(ref="#/components/schemas/stationary-event")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(ListStationaryEventRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/stationary-events",
     *      summary="Store a newly created Stationary event in storage",
     *      tags={"Admin Stationary Events"},
     *      description="Store Stationary Event",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/stationary-event-create-request")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=201,
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
     *                  ref="#/components/schemas/stationary-event"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateStationaryEventRequest $request):JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/stationary-events/{id}",
     *      summary="Display the specified Stationary Event",
     *      tags={"Admin Stationary Events"},
     *      description="Get Stationary Event",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Stationary event",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
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
     *                  ref="#/components/schemas/stationary-event"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show(ReadStationaryEventRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *      path="/api/admin/stationary-events/{id}",
     *      summary="Update the specified Stationary Event in storage",
     *      tags={"Admin Stationary Events"},
     *      description="Update Stationary Events",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Stationary Events",
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
     *              @OA\Schema(ref="#/components/schemas/stationary-event-update-request"),
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
     *                  ref="#/components/schemas/stationary-event"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update(UpdateStationaryEventRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/stationary-events/{id}",
     *      summary="Remove the specified Stationary Event from storage",
     *      tags={"Admin Stationary Events"},
     *      description="Delete Stationary Event",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Stationary Event",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
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
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function delete(DeleteStationaryEventRequest $request): JsonResponse;
}
