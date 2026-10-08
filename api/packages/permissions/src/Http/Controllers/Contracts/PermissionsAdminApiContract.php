<?php

namespace EscolaLms\Permissions\Http\Controllers\Contracts;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Permissions\Http\Requests\RoleCreateRequest;
use EscolaLms\Permissions\Http\Requests\RoleDeleteRequest;
use EscolaLms\Permissions\Http\Requests\RoleListingRequest;
use EscolaLms\Permissions\Http\Requests\RoleReadRequest;
use EscolaLms\Permissions\Http\Requests\RoleUpdateRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *     schema="Role",
 *     required={"name"},
 *     @OA\Property(
 *          property="id",
 *          type="integer",
 *          description="id "
 *     ),
 *     @OA\Property(
 *          property="name",
 *          type="string",
 *          description="name "
 *     ),
 *     @OA\Property(
 *          property="guard_name",
 *          type="string",
 *          description="Guard Name"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Permission",
 *     required={"id", "name","guard_name", "assigned"},
 *     @OA\Property(
 *          property="id",
 *          type="integer",
 *          description="id "
 *     ),
 *     @OA\Property(
 *          property="name",
 *          type="string",
 *          description="name "
 *     ),
 *     @OA\Property(
 *          property="guard_name",
 *          type="string",
 *          description="Guard Name"
 *     ),
 *     @OA\Property(
 *          property="assigned",
 *          type="boolean",
 *          description="Is permission assigned to current role"
 *     )
 * )
 *
 */


interface PermissionsAdminApiContract
{

    /**
     * @OA\Get(
     *     path="/api/admin/roles",
     *     summary="Lists available roles",
     *     tags={"Admin Permissions"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Response(
     *         response=200,
     *         description="list of available roles",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(
     *                type="array",
     *                description="Roles",
     *                @OA\Items(
     *                    ref="#/components/schemas/Role"
     *                )
     *            )
     *         )
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param RoleListingRequest $request
     * @return JsonResponse
     */
    public function index(RoleListingRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/admin/roles",
     *     summary="Find or Create a Role",
     *     tags={"Admin Permissions"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         description="Role attributes. Just name, rest are omitted",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Role")
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Role created of found successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="one of the parameters has invalid format",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param RoleCreateRequest $request
     * @return JsonResponse
     */
    public function create(RoleCreateRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *     path="/api/admin/roles/{name}",
     *     summary="Update an existing Role identified by name by list or new permissions",
     *     tags={"Admin Permissions"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable role identifier",
     *         in="path",
     *         name="name",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         description="Array of named permissions",
     *         required=true,
     *         @OA\MediaType(
     *           mediaType="application/json",
     *             @OA\Schema(
     *                @OA\Property(
     *                  property="permissions",
     *                  description="The images URL",
     *                  type="array",
     *                  description="Permissions",
     *                  @OA\Items(
     *                    type="string"
     *                  )
     *                )
     *             )
     *          )
     *
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="role updated successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=400,
     *          description="cannot find a role with provided slug identifier",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="one of the parameters has invalid format",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param RoleUpdateRequest $request
     * @return JsonResponse
     */
    public function update(RoleUpdateRequest $request, string $name): JsonResponse;

    /**
     * @OA\Delete(
     *     path="/api/admin/roles/{name}",
     *     summary="Delete a Role identified by a name",
     *     tags={"Admin Permissions"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable role identifier",
     *         in="path",
     *         name="name",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="role deleted successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=400,
     *          description="cannot find a role with provided slug identifier",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param RoleDeleteRequest $request
     * @return JsonResponse
     */
    public function delete(RoleDeleteRequest $request, string $name): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/roles/{name}",
     *     summary="Read a role and returns list of all permissions ",
     *     tags={"Admin Permissions"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable template identifier",
     *         in="path",
     *         name="name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="list of available roles",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(
     *                type="array",
     *                description="Permissions",
     *                @OA\Items(
     *                    ref="#/components/schemas/Permission"
     *                )
     *            )
     *         )
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param RoleReadRequest $request
     * @return JsonResponse
     */
    public function show(RoleReadRequest $request, string $name): JsonResponse;
}
