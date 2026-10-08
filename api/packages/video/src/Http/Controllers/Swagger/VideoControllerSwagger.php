<?php

namespace EscolaLms\Video\Http\Controllers\Swagger;

use EscolaLms\Video\Http\Requests\VideoProcessStateRequest;
use Illuminate\Http\JsonResponse;

interface VideoControllerSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/video/states",
     *     summary="List of processed videos",
     *     tags={"Video"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Video process state (error, finished, coding)",
     *         in="query",
     *         name="state",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available",
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
     * @param VideoProcessStateRequest $request
     * @return JsonResponse
     */
    public function states(VideoProcessStateRequest $request): JsonResponse;
}
