<?php

namespace Ulams\Tutor\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Tutor\Http\Requests\AskRequest;

interface TutorSwagger
{
    /**
     * @OA\Get(
     *   path="/api/tutor/courses/{course}/status", operationId="tutor.courses.by-course.status.get",
     *   summary="Whether the course tutor is available to the learner, how many questions are left today and whether a quiz attempt is open",
     *   tags={"Tutor"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="`enabled` (tutor on for the tenant), `aiEnabled` (AI on), `remaining` and `dailyLimit` questions, `attemptActive` (a quiz attempt is open: the tutor explains concepts only)", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="The learner may not attend the course"),
     *   @OA\Response(response=404, description="Unknown course")
     * )
     */
    public function status(Request $request, int $course): JsonResponse;

    /**
     * @OA\Post(
     *   path="/api/tutor/courses/{course}/messages", operationId="tutor.courses.by-course.messages.post",
     *   summary="Ask the course tutor a question; it answers only from the course, with citations",
     *   tags={"Tutor"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"text"},
     *     @OA\Property(property="text", type="string", maxLength=1000),
     *     @OA\Property(property="conversationId", type="string", nullable=true),
     *     @OA\Property(property="topicId", type="integer", nullable=true, description="The topic the learner has open: its content is added to the search")
     *   )),
     *   @OA\Response(response=200, description="`answer`, `citations` (label, topic, source fragments), `refusal` (none, out_of_scope, attempt_active, unsafe), `inScope`, `remaining` questions today", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="The learner may not attend the course"),
     *   @OA\Response(response=404, description="Unknown course or conversation"),
     *   @OA\Response(response=429, description="Daily or monthly limit reached, or too many requests"),
     *   @OA\Response(response=503, description="The tutor is switched off or AI is disabled")
     * )
     */
    public function ask(AskRequest $request, int $course): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/tutor/courses/{course}/conversations", operationId="tutor.courses.by-course.conversations.get",
     *   summary="The learner's own conversations in a course",
     *   tags={"Tutor"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Newest first", @OA\JsonContent(ref="#/components/schemas/ApiJson"))
     * )
     */
    public function conversations(Request $request, int $course): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/tutor/conversations/{conversation}", operationId="tutor.conversations.by-conversation.get",
     *   summary="One of the learner's conversations with its messages",
     *   tags={"Tutor"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Messages in order", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=404, description="Not the learner's conversation")
     * )
     */
    public function show(Request $request, string $conversation): JsonResponse;

    /**
     * @OA\Delete(
     *   path="/api/tutor/conversations/{conversation}", operationId="tutor.conversations.by-conversation.delete",
     *   summary="Delete one of the learner's conversations",
     *   tags={"Tutor"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Deleted", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=404, description="Not the learner's conversation")
     * )
     */
    public function destroy(Request $request, string $conversation): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/admin/tutor/courses/{course}/analytics", operationId="admin.tutor.courses.by-course.analytics.get",
     *   summary="Anonymous tutor analytics for a course: questions, out-of-scope share and refusals during attempts",
     *   tags={"Tutor"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Counts only; `smallGroup` below the minimum group size. No transcripts, no names", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="Needs learner_insights_view or authorship of the course"),
     *   @OA\Response(response=404, description="Unknown course")
     * )
     */
    public function analytics(Request $request, int $course): JsonResponse;
}
