<?php

namespace EscolaLms\CourseAccess\Http\Controllers\Admin\Swagger;

use EscolaLms\CourseAccess\Http\Requests\Admin\AddAccessAPIRequest;
use EscolaLms\CourseAccess\Http\Requests\Admin\ListAccessAPIRequest;
use EscolaLms\CourseAccess\Http\Requests\Admin\RemoveAccessAPIRequest;
use EscolaLms\CourseAccess\Http\Requests\Admin\SetAccessAPIRequest;
use Illuminate\Http\JsonResponse;

interface CoursesAccessAPISwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/courses/{id}/access",
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
     *      path="/api/admin/courses/{id}/access/add",
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
     *      path="/api/admin/courses/{id}/access/remove",
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
     *      path="/api/admin/courses/{id}/access/set",
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
