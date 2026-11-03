<?php

namespace Ulams\Courses\Http\Controllers\Swagger;

use Ulams\Courses\Http\Requests\CourseAssignableUserListRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface CourseAuthorsAPISwagger
{
    /**
     * @OA\Get(
     *      tags={"Courses"},
     *      path="/api/tutors", operationId="tutors.get",
     *      description="Get Tutors",
     *      security={
     *          {"passport": {}},
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
    public function index(Request $request): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Courses"},
     *      path="/api/tutors/{id}", operationId="tutors.by-id.get",
     *      description="Get tutor course/author ID ",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="number",
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
    public function show($id, Request $request): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Admin Courses"},
     *      path="/api/admin/courses/users/assignable", operationId="admin.courses.users.assignable.get",
     *      description="Get users assignable to course",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="search",
     *          required=false,
     *          in="query",
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
    public function assignableUsers(CourseAssignableUserListRequest $request): JsonResponse;
}
