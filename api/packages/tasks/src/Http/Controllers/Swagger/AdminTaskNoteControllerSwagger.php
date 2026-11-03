<?php

namespace Ulams\Tasks\Http\Controllers\Swagger;

use Ulams\Tasks\Http\Requests\Admin\AdminCreateTaskNoteRequest;
use Ulams\Tasks\Http\Requests\Admin\AdminDeleteTaskNoteRequest;
use Ulams\Tasks\Http\Requests\Admin\AdminUpdateTaskNoteRequest;
use Illuminate\Http\JsonResponse;

interface AdminTaskNoteControllerSwagger
{

    /**
     * @OA\Post(
     *      path="/api/admin/tasks/notes", operationId="admin.tasks.notes.post",
     *      summary="Store a newly created task note",
     *      tags={"Admin Tasks Notes"},
     *      description="Store Task Note",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/TaskNoteCreateRequest")
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
     *                      ref="#/components/schemas/TaskNoteResource"
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
    public function create(AdminCreateTaskNoteRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *      path="/api/admin/tasks/notes/{id}", operationId="admin.tasks.notes.by-id.patch",
     *      summary="Update task note",
     *      tags={"Admin Tasks Notes"},
     *      description="Update Task Note",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of task note",
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
     *              @OA\Schema(ref="#/components/schemas/TaskNoteCreateRequest")
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
     *                      ref="#/components/schemas/TaskNoteResource"
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
    public function update(AdminUpdateTaskNoteRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/tasks/notes/{id}", operationId="admin.tasks.notes.by-id.delete",
     *      summary="Remove the specified task notes",
     *      tags={"Admin Tasks Notes"},
     *      description="Delete Task Note",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of task note",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
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
    public function delete(AdminDeleteTaskNoteRequest $request): JsonResponse;
}
