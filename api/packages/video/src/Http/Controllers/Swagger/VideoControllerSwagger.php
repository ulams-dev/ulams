<?php

namespace Ulams\Video\Http\Controllers\Swagger;

use Ulams\Video\Http\Requests\VideoProcessStateRequest;
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
     *         description="Processed videos with the state of their conversion to HLS",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="topic_id", type="integer", nullable=true, example=12),
     *                      @OA\Property(property="topic_title", type="string", nullable=true),
     *                      @OA\Property(property="json", type="object", nullable=true, description="The topic json, with the ffmpeg state under `ffmpeg`"),
     *                      @OA\Property(property="state", type="string", nullable=true, enum={"coding", "finished", "error"}),
     *                  )
     *              ),
     *          ),
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
