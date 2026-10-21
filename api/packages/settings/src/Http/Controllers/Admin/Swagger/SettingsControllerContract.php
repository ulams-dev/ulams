<?php

namespace Ulams\Settings\Http\Controllers\Admin\Swagger;

use Ulams\Settings\Http\Requests\Admin\SettingsCreateRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsDeleteRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsListRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsReadRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsUpdateRequest;
use Illuminate\Http\JsonResponse;

interface SettingsControllerContract
{
    /**
     * @OA\Get(
     *      tags={"Admin Settings"},
     *      path="/api/admin/settings", operationId="admin.settings.get",
     *      description="Get Settings paginated",
     *      security={
     *         {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="group",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
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
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function index(SettingsListRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     tags={"Admin Settings"},
     *      path="/api/admin/settings/{id}", operationId="admin.settings.by-id.get",
     *      description="Get single settings",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function show($id, SettingsReadRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     tags={"Admin Settings"},
     *     path="/api/admin/settings", operationId="admin.settings.post",
     *     summary="Settings create",
     *     description="Create single setting",
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     * )
     */
    public function store(SettingsCreateRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *     tags={"Admin Settings"},
     *     path="/api/admin/settings/{id}", operationId="admin.settings.by-id.put",
     *     summary="Update setting",
     *     description="Update single setting",
     *     security={
     *         {"passport": {}},
     *     },    
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     * )
     */
    public function update($id, SettingsUpdateRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *     tags={"Admin Settings"},
     *     security={
     *         {"passport": {}},
     *     },
     *     path="/api/admin/settings/{id}", operationId="admin.settings.by-id.delete",
     *     summary="Destroy setting",
     *     description="Destroy the specified setting",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     * )
     */
    public function destroy($id, SettingsDeleteRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Admin Settings"},
     *      path="/api/admin/settings/groups", operationId="admin.settings.groups.get",
     *      description="Get Settings unique groups",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function groups(SettingsListRequest $request): JsonResponse;
}
