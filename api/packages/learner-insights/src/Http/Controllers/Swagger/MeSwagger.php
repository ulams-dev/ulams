<?php

namespace Ulams\LearnerInsights\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\LearnerInsights\Http\Requests\MySettingsRequest;

interface MeSwagger
{
    /**
     * @OA\Get(
     *   path="/api/learner-insights/me", operationId="learner-insights.me.get",
     *   summary="The learner's own statuses with reasons, their privacy settings and the retention periods",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Response(response=200, description="`enabled` (module on for the tenant), `settings`, `courses` (status and reasons per course and element), `retention`", @OA\JsonContent(ref="#/components/schemas/ApiJson"))
     * )
     */
    public function show(Request $request): JsonResponse;

    /**
     * @OA\Put(
     *   path="/api/learner-insights/me/settings", operationId="learner-insights.me.settings.put",
     *   summary="Opt out of personalisation, switch personal help or nudges, give or withdraw consent",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     @OA\Property(property="enabled", type="boolean"),
     *     @OA\Property(property="help", type="boolean"),
     *     @OA\Property(property="nudges", type="boolean"),
     *     @OA\Property(property="consent", type="boolean"),
     *     @OA\Property(property="eraseExisting", type="boolean", description="With enabled=false: also delete what was recorded before")
     *   )),
     *   @OA\Response(response=200, description="The settings after the change", @OA\JsonContent(ref="#/components/schemas/ApiJson")),
     *   @OA\Response(response=422, description="Invalid value")
     * )
     */
    public function updateSettings(MySettingsRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *   path="/api/learner-insights/me/export", operationId="learner-insights.me.export.get",
     *   summary="Download everything stored about the learner (signals, statuses, transitions) as JSON",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Response(response=200, description="A JSON file")
     * )
     */
    public function export(Request $request): StreamedResponse;

    /**
     * @OA\Delete(
     *   path="/api/learner-insights/me", operationId="learner-insights.me.delete",
     *   summary="Erase the learner's personalisation data (the opt-out and consent settings stay)",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Response(response=200, description="Rows deleted per table", @OA\JsonContent(ref="#/components/schemas/ApiJson"))
     * )
     */
    public function erase(Request $request): JsonResponse;
}
