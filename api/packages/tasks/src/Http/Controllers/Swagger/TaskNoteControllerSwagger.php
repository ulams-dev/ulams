<?php

namespace Ulams\Tasks\Http\Controllers\Swagger;

use Ulams\Tasks\Http\Requests\CreateTaskNoteRequest;
use Ulams\Tasks\Http\Requests\DeleteTaskNoteRequest;
use Ulams\Tasks\Http\Requests\UpdateTaskNoteRequest;
use Illuminate\Http\JsonResponse;

interface TaskNoteControllerSwagger
{

    /**
     * @OA\Post(
     *      path="/api/tasks/notes",
     *      summary="Store a newly created task note",
     *      tags={"Tasks Notes"},
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
    public function create(CreateTaskNoteRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *      path="/api/tasks/notes/{id}",
     *      summary="Update task note",
     *      tags={"Tasks Notes"},
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
    public function update(UpdateTaskNoteRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/tasks/notes/{id}",
     *      summary="Remove the specified task notes",
     *      tags={"Tasks Notes"},
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
    public function delete(DeleteTaskNoteRequest $request): JsonResponse;
}
