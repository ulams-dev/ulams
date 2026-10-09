<?php

namespace Ulams\Cmi5\Http\Controllers\Swagger;

use Ulams\Cmi5\Http\Requests\Cmi5DeleteRequest;
use Ulams\Cmi5\Http\Requests\Cmi5ListRequest;
use Ulams\Cmi5\Http\Requests\Cmi5ReadRequest;
use Ulams\Cmi5\Http\Requests\Cmi5UploadRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

interface Cmi5ControllerSwagger
{
    /**
     * @OA\Post(
     *     path="/api/admin/cmi5",
     *     summary="Convert ZIP Cmi5 Package into Ulams LMS Cmi5 storage",
     *     tags={"cmi5"},
     *     security={
     *         {"passport": {}},
     *     },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="file",
     *                      type="string",
     *                      format="binary"
     *                  )
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Cmi5 data",
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
     * @param Cmi5UploadRequest $request
     * @return JsonResponse
     */
    public function upload(Cmi5UploadRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/cmi5/player/{cmi5AuId}",
     *     summary="Launch a cmi5 AU",
     *     description="Needs `cmi5_read` (students have it). Returns the player page, or with `format=json` the launch URL on the tenant content origin. The URL carries a one-time launch token, never the learner's access token (ADR 0046).",
     *     tags={"cmi5"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique id cmi5 au identifier",
     *         in="path",
     *         name="cmi5AuId",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="`json` returns {data: {url, origin}} instead of the player page",
     *         in="query",
     *         name="format",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"json"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Course id",
     *         in="query",
     *         name="course_id",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Topic id",
     *         in="query",
     *         name="topic_id",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="",
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
     * @param Cmi5ReadRequest $request
     * @param int $cmi5AuId
     * @return View|JsonResponse
     */
    public function read(Cmi5ReadRequest $request, int $cmi5AuId): View|JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/cmi5/",
     *     summary="Get a listing of the cmi5",
     *     tags={"cmi5"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="page",
     *         in="query",
     *         name="page",
     *         @OA\Schema(
     *             type="number"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="per_page",
     *         in="query",
     *         name="per_page",
     *         @OA\Schema(
     *             type="number"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available cmi5s",
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
     * @param Cmi5ListRequest $request
     * @return JsonResponse
     */
    public function list(Cmi5ListRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *     path="/api/admin/cmi5/{id}",
     *     summary="Delete cmi5 package by id",
     *     tags={"cmi5"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Cmi5 id",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="number"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cmi5 deleted successfully",
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
     * @param Cmi5DeleteRequest $request
     * @return JsonResponse
     */
    public function delete(Cmi5DeleteRequest $request): JsonResponse;
}
