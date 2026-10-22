<?php

namespace Ulams\LearnerInsights\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ImproveSwagger
{
    /**
     * @OA\Post(
     *   path="/api/admin/learner-insights/courses/{course}/elements/{elementKey}/improve", operationId="admin.learner-insights.courses.by-course.elements.by-element-key.improve.post",
     *   summary="Propose an improvement for an element learners struggle with: the estimate and the aggregated evidence first, then (confirmEstimate) a Living Course proposal of kind insights",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="elementKey", in="path", required=true, description="The element key of the insights table (a Course Builder element id)", @OA\Schema(type="string")),
     *   @OA\RequestBody(@OA\JsonContent(@OA\Property(property="confirmEstimate", type="boolean"), @OA\Property(property="period", type="integer", enum={7, 30, 90}, default=30))),
     *   @OA\Response(response=200, description="Without confirmEstimate: `estimateUsd` and the `evidence` that would be sent (aggregates only); or the open proposal of the element (`kept_existing`)", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=201, description="The proposal was created and its analysis started; review it as a Living Course proposal", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="Needs authorship of the course or learner_insights_view, and authorship of its builder session or living_course_review"),
     *   @OA\Response(response=404, description="Unknown course, or the element was not built with the Course Builder"),
     *   @OA\Response(response=422, description="Group below the minimum size, nothing to cite, element not improvable, or the budget blocks the analysis"),
     *   @OA\Response(response=429, description="The daily limit of improvement proposals of the course is reached"),
     *   @OA\Response(response=503, description="AI is disabled")
     * )
     */
    public function store(Request $request, int $course, string $elementKey): JsonResponse;
}
