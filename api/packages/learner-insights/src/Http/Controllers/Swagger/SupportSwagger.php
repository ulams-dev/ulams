<?php

namespace Ulams\LearnerInsights\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\LearnerInsights\Http\Requests\FeedbackRequest;
use Ulams\LearnerInsights\Http\Requests\ReflectionRequest;

interface SupportSwagger
{
    /**
     * @OA\Get(
     *   path="/api/learner-insights/courses/{course}/topics/{topic}/support", operationId="learner-insights.courses.by-course.topics.by-topic.support.get",
     *   summary="Extra help for the learner in a topic, with the reasons it was offered",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="`enabled`, and per assignment: `status` (generating or ready), `kind`, `document` (declarative blocks), `citations`, `reasons`, `why`, `feedback`", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="The learner may not attend the course"),
     *   @OA\Response(response=404, description="Unknown topic or the topic is not in the course")
     * )
     */
    public function index(Request $request, int $course, int $topic): JsonResponse;

    /**
     * @OA\Post(
     *   path="/api/learner-insights/assignments/{assignment}/feedback", operationId="learner-insights.assignments.by-assignment.feedback.post",
     *   summary="Say whether a piece of extra help was useful",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="assignment", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"helpful"}, @OA\Property(property="helpful", type="boolean"))),
     *   @OA\Response(response=200, description="Saved", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=404, description="Not the learner's assignment")
     * )
     */
    public function feedback(FeedbackRequest $request, string $assignment): JsonResponse;

    /**
     * @OA\Post(
     *   path="/api/learner-insights/assignments/{assignment}/hide", operationId="learner-insights.assignments.by-assignment.hide.post",
     *   summary="Hide a piece of extra help",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="assignment", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Hidden", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=404, description="Not the learner's assignment")
     * )
     */
    public function hide(Request $request, string $assignment): JsonResponse;

    /**
     * @OA\Post(
     *   path="/api/learner-insights/assignments/{assignment}/reflection", operationId="learner-insights.assignments.by-assignment.reflection.post",
     *   summary="Get feedback on the learner's own explanation of the idea (the text is sent to the AI provider and not stored)",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="assignment", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"text"}, @OA\Property(property="text", type="string", maxLength=2000))),
     *   @OA\Response(response=200, description="`correct`, `missing`, `followUp`, `citations`, `remaining`", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=404, description="Not the learner's assignment"),
     *   @OA\Response(response=429, description="Daily limit reached"),
     *   @OA\Response(response=503, description="AI is disabled or the feedback could not be produced")
     * )
     */
    public function reflection(ReflectionRequest $request, string $assignment): JsonResponse;
}
