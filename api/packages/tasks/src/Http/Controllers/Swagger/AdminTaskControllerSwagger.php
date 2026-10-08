<?php

namespace EscolaLms\Tasks\Http\Controllers\Swagger;

use EscolaLms\Tasks\Http\Requests\Admin\AdminCompleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminCreateTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminDeleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminDetailsTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminIncompleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminListTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminUpdateTaskRequest;
use Illuminate\Http\JsonResponse;

interface AdminTaskControllerSwagger
{

    /**
     * @OA\Post(
     *      path="/api/admin/tasks",
     *      summary="Store a newly created task",
     *      tags={"Admin Tasks"},
     *      description="Store Task",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/AdminTaskCreateRequest")
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
     *                      ref="#/components/schemas/TaskResource"
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
    public function create(AdminCreateTaskRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *      path="/api/admin/tasks/{id}",
     *      summary="Update the specified task",
     *      tags={"Admin Tasks"},
     *      description="Update Task",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of task",
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
     *              @OA\Schema(ref="#/components/schemas/AdminTaskCreateRequest")
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
     *                      ref="#/components/schemas/TaskResource"
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
    public function update(AdminUpdateTaskRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/tasks/{id}",
     *      summary="Remove the specified task",
     *      tags={"Admin Tasks"},
     *      description="Delete Task",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of task",
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
    public function delete(AdminDeleteTaskRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/tasks/complete/{id}",
     *      summary="Complete task",
     *      tags={"Admin Tasks"},
     *      description="Complete Task",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of task",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
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
     *                      ref="#/components/schemas/TaskResource"
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
    public function complete(AdminCompleteTaskRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/tasks/incomplete/{id}",
     *      summary="Incomplete task",
     *      tags={"Admin Tasks"},
     *      description="Incomplete Task",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of task",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
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
     *                      ref="#/components/schemas/TaskResource"
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
    public function incomplete(AdminIncompleteTaskRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/tasks",
     *      summary="Get a listing of the tasks",
     *      tags={"Admin Tasks"},
     *      description="Get all Tasks",
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
     *          name="title",
     *          description="Task title %LIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="type",
     *          description="Task type",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="user_id",
     *          description="Task user_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="created_by_id",
     *          description="Task created_by_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="related_type",
     *          description="Task related_type",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="related_id",
     *          description="Task related_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="related_ids[]",
     *          description="An array of related_id",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="array", @OA\Items(type="number")),
     *      ),
     *      @OA\Parameter(
     *          name="due_date_from",
     *          description="Due date from",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="due_date_to",
     *          description="Due date to",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
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
     *                  @OA\Items(ref="#/components/schemas/TaskResource")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function findAll(AdminListTaskRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/tasks/{id}",
     *      summary="Task details",
     *      tags={"Admin Tasks"},
     *      description="Get task details by ID",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of task",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
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
     *                      ref="#/components/schemas/TaskDetailsResource"
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
    public function find(AdminDetailsTaskRequest $request): JsonResponse;
}
