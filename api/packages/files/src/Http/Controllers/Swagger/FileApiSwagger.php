<?php

namespace EscolaLms\Files\Http\Controllers\Swagger;

use EscolaLms\Files\Http\Requests\FileDeleteRequest;
use EscolaLms\Files\Http\Requests\FileFindByNameRequest;
use EscolaLms\Files\Http\Requests\FileListingRequest;
use EscolaLms\Files\Http\Requests\FileMoveRequest;
use EscolaLms\Files\Http\Requests\FileUploadRequest;
use Illuminate\Http\JsonResponse;

if (file_exists(__DIR__.'/../../oa_version.php')) {
    require __DIR__.'/../../oa_version.php';
}

/**
 * SWAGGER_VERSION
 */
interface FileApiSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/file/list",
     *     summary="Lists files prefixed by given directory name",
     *     tags={"Files"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         name="directory",
     *         in="query",
     *         @OA\Schema(
     *             type="string",
     *             default="/",
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(
     *             type="int",
     *             default="1",
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         @OA\Schema(
     *             type="int",
     *             default="50",
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(
     *                type="array",
     *                @OA\Items(
     *                    type="object",
     *                    @OA\Property(
     *                        property="name",
     *                        type="string",
     *                    ),
     *                    @OA\Property(
     *                        property="created_at",
     *                        type="string",
     *                        format="datetime",
     *                    ),
     *                    @OA\Property(
     *                        property="mime",
     *                        type="string",
     *                        format="mime",
     *                    ),
     *                    @OA\Property(
     *                        property="url",
     *                        type="string",
     *                        format="url",
     *                    ),
     *                ),
     *            )
     *         )
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=302,
     *          description="request contains invalid parameters",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=405,
     *          description="Target directory access is not allowed",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param FileListingRequest $request
     * @return JsonResponse
     */
    public function list(FileListingRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/file/find",
     *     summary="Lists files found by name",
     *     tags={"Files"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         name="directory",
     *         in="query",
     *         @OA\Schema(
     *             type="string",
     *             default="/",
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         required=true,
     *         in="query",
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(
     *             type="int",
     *             default="1",
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         @OA\Schema(
     *             type="int",
     *             default="50",
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(
     *                type="array",
     *                @OA\Items(
     *                    type="object",
     *                    @OA\Property(
     *                        property="name",
     *                        type="string",
     *                    ),
     *                    @OA\Property(
     *                        property="created_at",
     *                        type="string",
     *                        format="datetime",
     *                    ),
     *                    @OA\Property(
     *                        property="mime",
     *                        type="string",
     *                        format="mime",
     *                    ),
     *                    @OA\Property(
     *                        property="url",
     *                        type="string",
     *                        format="url",
     *                    ),
     *                ),
     *            )
     *         )
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=302,
     *          description="request contains invalid parameters",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=405,
     *          description="Target directory access is not allowed",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param FileFindByNameRequest $request
     * @return JsonResponse
     */
    public function findByName(FileFindByNameRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/admin/file/upload",
     *     summary="Upload files using multipart form-data",
     *     tags={"Files"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *            mediaType="multipart/form-data",
     *            @OA\Schema(
     *                type="object",
     *                required={"file[]","target"},
     *                @OA\Property(
     *                    property="file[]",
     *                    type="array",
     *                    @OA\Items(
     *                        type="string",
     *                        format="binary",
     *                    ),
     *                ),
     *                @OA\Property(
     *                    property="target",
     *                    type="string",
     *                    default="/",
     *                ),
     *            )
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
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
     *          response=409,
     *          description="one of the uploaded files already exists",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param FileUploadRequest $request
     * @return JsonResponse
     */
    public function upload(FileUploadRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/admin/file/move",
     *     summary="Move the file from one path to another",
     *     tags={"Files"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *            mediaType="multipart/form-data",
     *            @OA\Schema(
     *                type="object",
     *                required={"source_url","destination_url"},
     *                @OA\Property(property="source_url", type="string"),
     *                @OA\Property(property="destination_url", type="string"),
     *            )
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *      ),
     *     @OA\Response(
     *          response=302,
     *          description="invalid request",
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
     *          description="Couldn't perform the action",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param FileMoveRequest $request
     * @return JsonResponse
     */
    public function move(FileMoveRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *     path="/api/admin/file/delete",
     *     summary="Delete given file",
     *     tags={"Files"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         name="url",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *      ),
     *     @OA\Response(
     *          response=400,
     *          description="file doesn't exists",
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
     *          response=405,
     *          description="specified file is out of bounds of the allowed paths",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param FileDeleteRequest $request
     * @return JsonResponse
     */
    public function delete(FileDeleteRequest $request): JsonResponse;
}
