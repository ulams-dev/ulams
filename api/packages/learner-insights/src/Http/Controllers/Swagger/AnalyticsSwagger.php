<?php

namespace Ulams\LearnerInsights\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface AnalyticsSwagger
{
    /**
     * @OA\Get(
     *   path="/api/admin/learner-insights/courses/{course}/summary",
     *   summary="Per-course analytics: progress, completion, quiz performance, status distribution and interventions",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="integer", enum={7, 30, 90}, default=30)),
     *   @OA\Response(response=200, description="Aggregates; `smallGroup` is true (and only `enrolled` is given) below the minimum group size"),
     *   @OA\Response(response=403, description="Needs learner_insights_view or authorship of the course"),
     *   @OA\Response(response=404, description="Unknown course")
     * )
     */
    public function summary(Request $request, int $course): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/admin/learner-insights/courses/{course}/elements",
     *   summary="The sections that confuse people: per element struggle rate, remediation and recovery, with the top reasons",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="integer", enum={7, 30, 90}, default=30)),
     *   @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", enum={"struggle", "active", "recovery"})),
     *   @OA\Response(response=200, description="Elements; those with fewer active learners than the minimum group are returned as `hidden`"),
     *   @OA\Response(response=403, description="Needs learner_insights_view or authorship of the course"),
     *   @OA\Response(response=404, description="Unknown course")
     * )
     */
    public function elements(Request $request, int $course): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/admin/learner-insights/courses/{course}/learners",
     *   summary="Learners who are struggling or at risk, with the reasons (individual data)",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"struggling", "at_risk", "on_track"})),
     *   @OA\Response(response=200, description="Individual statuses"),
     *   @OA\Response(response=403, description="Needs learner_insights_view_individuals, or the tenant setting that lets course authors see individuals"),
     *   @OA\Response(response=404, description="Unknown course")
     * )
     */
    public function learners(Request $request, int $course): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/admin/learner-insights/learners/{user}/courses/{course}/timeline",
     *   summary="One learner's signals in a course with element labels (support)",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Newest first"),
     *   @OA\Response(response=403, description="Needs learner_insights_view_individuals"),
     *   @OA\Response(response=404, description="Unknown course or learner")
     * )
     */
    public function timeline(Request $request, int $user, int $course): JsonResponse;

    /**
     * @OA\Post(
     *   path="/api/admin/learner-insights/backfill",
     *   summary="Build signals from existing data for a course or the whole tenant (queued)",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\RequestBody(@OA\JsonContent(@OA\Property(property="courseId", type="integer"))),
     *   @OA\Response(response=202, description="Queued"),
     *   @OA\Response(response=403, description="Needs learner_insights_manage")
     * )
     */
    public function backfill(Request $request): JsonResponse;
}
