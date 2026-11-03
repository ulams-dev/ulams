<?php

namespace Ulams\LearnerInsights\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\LearnerInsights\Http\Requests\CourseSettingsRequest;

interface SettingsSwagger
{
    /**
     * @OA\Get(
     *   path="/api/admin/learner-insights/settings", operationId="admin.learner-insights.settings.get",
     *   summary="The effective rule thresholds of the tenant (defaults and tenant settings)",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Response(response=200, description="Rules by name, each with its parameters", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="Needs learner_insights_manage")
     * )
     */
    public function tenant(Request $request): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/admin/learner-insights/courses/{course}/settings", operationId="admin.learner-insights.courses.by-course.settings.get",
     *   summary="Thresholds of one course: the overrides and the effective values",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="`overrides` and `effective`", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="Needs learner_insights_manage"),
     *   @OA\Response(response=404, description="Unknown course")
     * )
     */
    public function showCourse(Request $request, int $course): JsonResponse;

    /**
     * @OA\Put(
     *   path="/api/admin/learner-insights/courses/{course}/settings", operationId="admin.learner-insights.courses.by-course.settings.put",
     *   summary="Replace the threshold overrides of one course",
     *   tags={"Learner insights admin"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="rules", type="object", example={"inactivity": {"inactive_days": 5}}))),
     *   @OA\Response(response=200, description="`overrides` and `effective`", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=403, description="Needs learner_insights_manage"),
     *   @OA\Response(response=404, description="Unknown course"),
     *   @OA\Response(response=422, description="Unknown rule or parameter, or an invalid value")
     * )
     */
    public function updateCourse(CourseSettingsRequest $request, int $course): JsonResponse;
}
