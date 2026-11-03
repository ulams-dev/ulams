<?php

namespace Ulams\LearnerInsights\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\LearnerInsights\Http\Requests\ClientSignalRequest;
use Ulams\LearnerInsights\Services\SignalRecorder;

interface ClientSignalSwagger
{
    /**
     * @OA\Post(
     *   path="/api/learner-insights/signals", operationId="learner-insights.signals.post",
     *   summary="Record a media signal (replay, seek back) from the player",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"type", "topicId", "ref"},
     *     @OA\Property(property="type", type="string", enum={"media.replayed", "media.seeked_back"}),
     *     @OA\Property(property="topicId", type="integer"),
     *     @OA\Property(property="value", type="number", description="Seconds"),
     *     @OA\Property(property="media", type="string", enum={"video", "audio"}),
     *     @OA\Property(property="ref", type="string", format="uuid", description="Client generated id, used to drop duplicates")
     *   )),
     *   @OA\Response(response=202, description="Accepted (nothing is stored when the module is off or the learner opted out)", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="The learner may not attend the course of the topic"),
     *   @OA\Response(response=404, description="Unknown topic"),
     *   @OA\Response(response=429, description="Too many signals")
     * )
     */
    public function store(ClientSignalRequest $request, SignalRecorder $recorder): JsonResponse;
}
