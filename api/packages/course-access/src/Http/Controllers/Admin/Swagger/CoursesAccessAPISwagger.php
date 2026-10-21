<?php

namespace Ulams\CourseAccess\Http\Controllers\Admin\Swagger;

use Ulams\CourseAccess\Http\Requests\Admin\AddAccessAPIRequest;
use Ulams\CourseAccess\Http\Requests\Admin\ListAccessAPIRequest;
use Ulams\CourseAccess\Http\Requests\Admin\RemoveAccessAPIRequest;
use Ulams\CourseAccess\Http\Requests\Admin\SetAccessAPIRequest;
use Illuminate\Http\JsonResponse;

interface CoursesAccessAPISwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/courses/{id}/access", operationId="admin.courses.by-id.access.get",
     *      summary="Get list of users and groups with access to the course",
     *      tags={"Admin Courses"},
     *      description="Get list of users and groups with access to the course",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of course",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiJson"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  @OA\Schema(
     *                      type="object",
     *                      @OA\Property(
     *                          property="groups",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/Group")
     *                      ),
     *                      @OA\Property(
     *                          property="users",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/User")
     *                      ),
     *                  ),
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function list(ListAccessAPIRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/courses/{id}/access/add", operationId="admin.courses.by-id.access.add.post",
     *      summary="Add users and groups with access to the course",
     *      tags={"Admin Courses"},
     *      description="Add users and groups with access to the course",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of course",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="users",
     *                  type="array",     
     *                  @OA\Items(
     *                      type="integer",
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="groups",
     *                  type="array",
     *                  @OA\Items(
     *                      type="integer",
     *                  )
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiJson"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  @OA\Schema(
     *                      type="object",
     *                      @OA\Property(
     *                          property="groups",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/Group")
     *                      ),
     *                      @OA\Property(
     *                          property="users",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/User")
     *                      ),
     *                  ),
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function add(AddAccessAPIRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/courses/{id}/access/remove", operationId="admin.courses.by-id.access.remove.post",
     *      summary="Remove users and groups with access to the course",
     *      tags={"Admin Courses"},
     *      description="Remove users and groups with access to the course",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of course",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="users",
     *                  type="array",     
     *                  @OA\Items(
     *                      type="integer",
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="groups",
     *                  type="array",
     *                  @OA\Items(
     *                      type="integer",
     *                  )
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiJson"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  @OA\Schema(
     *                      type="object",
     *                      @OA\Property(
     *                          property="groups",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/Group")
     *                      ),
     *                      @OA\Property(
     *                          property="users",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/User")
     *                      ),
     *                  ),
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function remove(RemoveAccessAPIRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/courses/{id}/access/set", operationId="admin.courses.by-id.access.set.post",
     *      summary="Set users and groups with access to the course",
     *      tags={"Admin Courses"},
     *      description="Set users and groups with access to the course",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of course",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="users",
     *                  type="array",     
     *                  @OA\Items(
     *                      type="integer",
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="groups",
     *                  type="array",
     *                  @OA\Items(
     *                      type="integer",
     *                  )
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiJson"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  @OA\Schema(
     *                      type="object",
     *                      @OA\Property(
     *                          property="groups",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/Group")
     *                      ),
     *                      @OA\Property(
     *                          property="users",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/User")
     *                      ),
     *                  ),
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function set(SetAccessAPIRequest $request): JsonResponse;
}
