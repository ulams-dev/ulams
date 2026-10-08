<?php

namespace EscolaLms\Tasks\Http\Controllers\Swagger;

use EscolaLms\Tasks\Http\Requests\CompleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\CreateTaskRequest;
use EscolaLms\Tasks\Http\Requests\DeleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\DetailsTaskRequest;
use EscolaLms\Tasks\Http\Requests\IncompleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\ListTaskRequest;
use EscolaLms\Tasks\Http\Requests\UpdateTaskRequest;
use Illuminate\Http\JsonResponse;

interface TaskControllerSwagger
{

    /**
     * @OA\Post(
     *      path="/api/tasks",
     *      summary="Store a newly created task",
     *      tags={"Tasks"},
     *      description="Store Task",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/TaskCreateRequest")
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
    public function create(CreateTaskRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *      path="/api/tasks/{id}",
     *      summary="Update task",
     *      tags={"Tasks"},
     *      description="Store Task",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/TaskCreateRequest")
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
    public function update(UpdateTaskRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/tasks/{id}",
     *      summary="Remove the specified task",
     *      tags={"Tasks"},
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
    public function delete(DeleteTaskRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/tasks/complete/{id}",
     *      summary="Complete task",
     *      tags={"Tasks"},
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
    public function complete(CompleteTaskRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/tasks/incomplete/{id}",
     *      summary="Incomplete task",
     *      tags={"Tasks"},
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
    public function incomplete(IncompleteTaskRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/tasks",
     *      summary="Get a listing of the tasks",
     *      tags={"Tasks"},
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
    public function findAll(ListTaskRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/tasks/{id}",
     *      summary="Task details",
     *      tags={"Tasks"},
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
    public function find(DetailsTaskRequest $request): JsonResponse;
}
