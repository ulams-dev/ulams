<?php

namespace EscolaLms\AssignWithoutAccount\Http\Controllers\Swagger;

use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionCreateRequest;
use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionDeleteRequest;
use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionListRequest;
use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionUpdateRequest;
use Illuminate\Http\JsonResponse;

interface UserSubmissionAdminControllerSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/user-submissions",
     *     summary="Lists available user submissions",
     *     tags={"Assign Without Account"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="User email",
     *         in="query",
     *         name="email",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="User submission status",
     *         in="query",
     *         name="status",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Morphable type",
     *         in="query",
     *         name="morphable_type",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Morphable id",
     *         in="query",
     *         name="morphable_id",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Order column",
     *         in="query",
     *         name="order_by",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Order direction",
     *         in="query",
     *         name="order",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available user submissions",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="Endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="User doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="Server-side error",
     *      ),
     * )
     *
     * @param UserSubmissionListRequest $request
     * @return JsonResponse
     */
    public function index(UserSubmissionListRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/admin/user-submissions",
     *     summary="Create a new user submission identified by id",
     *     tags={"Assign Without Account"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         description="User submission attributes",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UserSubmission")
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="User submission created successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="Endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="User doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="One of the parameters has invalid format",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="Server-side error",
     *      ),
     * )
     *
     * @param UserSubmissionCreateRequest $request
     * @return JsonResponse
     */
    public function create(UserSubmissionCreateRequest $request): JsonResponse;

    /**
     * @OA\Put (
     *     path="/api/admin/user-submissions/{id}",
     *     summary="Update an existing user submission identified by id",
     *     tags={"Assign Without Account"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable user submission identifier",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         description="User submission attributes",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UserSubmission")
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="User submission created successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="Endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="User doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="One of the parameters has invalid format",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="Server-side error",
     *      ),
     * )
     *
     * @param UserSubmissionUpdateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UserSubmissionUpdateRequest $request, int $id): JsonResponse;

    /**
     * @OA\Delete(
     *     path="/api/admin/user-submissions/{id}",
     *     summary="Delete user submission identified by a id",
     *     tags={"Assign Without Account"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable access url identifier",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="User submission eleted successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="Endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="User doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="Server-side error",
     *      ),
     * )
     *
     * @param UserSubmissionDeleteRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function delete(UserSubmissionDeleteRequest $request, int $id): JsonResponse;
}
