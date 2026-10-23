<?php

namespace Ulams\AuthoringMetrics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Ulams\AuthoringMetrics\Enums\AuthoringMetricsPermissionsEnum;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Services\ReviewTime;

/**
 * Anonymous review time (ADR 0194, AM-5). Off by default: while the tenant setting `authoring_metrics.review_time` is
 * off, posting answers 404 and nothing is stored. Only interactive sessions may post (scoped tokens are refused by the
 * route map), so an agent or a script cannot inflate review time. Nothing stored says who the person is.
 *
 * @OA\Get(path="/api/admin/authoring-metrics/activity/status", operationId="admin.authoring-metrics.activity.status", summary="Is review time recorded", tags={"Authoring metrics"}, security={{"passport": {}}},
 *     description="Whether the tenant records anonymous review time (the setting authoring_metrics.review_time) and the limits of the activity endpoint. Studio and admin ask before they start counting.",
 *     @OA\Response(response=200, description="enabled, maxMinutesPerCall, maxAgeMinutes", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 *
 * @OA\Post(path="/api/admin/authoring-metrics/activity", operationId="admin.authoring-metrics.activity.record", summary="Report active review minutes", tags={"Authoring metrics"}, security={{"passport": {}}},
 *     description="A browser reports the whole minutes in which the author was active on a course: the tab visible and keyboard, pointer or scroll input in the last 120 seconds. At most 10 minutes per call, none in the future and none older than 15 minutes. tab_nonce is random per page load. No user, address or element is stored. 404 when review time is not recorded or the course was not built with the Course Builder. Interactive sessions only.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"course_id","minutes","surface","tab_nonce"},
 *         @OA\Property(property="course_id", type="integer"), @OA\Property(property="minutes", type="array", @OA\Items(type="string", format="date-time")),
 *         @OA\Property(property="surface", type="string", enum={"studio","admin"}), @OA\Property(property="tab_nonce", type="string", minLength=22, maxLength=22))),
 *     @OA\Response(response=200, description="accepted: minutes stored", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Not recorded or not a builder course"), @OA\Response(response=422, description="Invalid minutes"))
 *
 * @OA\Delete(path="/api/admin/authoring-metrics/activity", operationId="admin.authoring-metrics.activity.delete", summary="Delete recorded review time", tags={"Authoring metrics"}, security={{"passport": {}}},
 *     description="Deletes every recorded review minute and daily total of the academy. Generation time, cost and counts are not personal and stay. Needs authoring_metrics_read. Interactive sessions only.",
 *     @OA\Response(response=200, description="minutes and days deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 */
class ActivityController extends Controller
{
    public function __construct(private readonly ReviewTime $time, private readonly CourseMetrics $metrics)
    {
    }

    public function status(): JsonResponse
    {
        return $this->ok(['enabled' => $this->time->enabled(), 'maxMinutesPerCall' => ReviewTime::MAX_MINUTES_PER_CALL, 'maxAgeMinutes' => ReviewTime::MAX_AGE_MINUTES]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->time->enabled(), 404);
        $data = $request->validate([
            'course_id' => ['required', 'integer'],
            'minutes' => ['required', 'array', 'min:1', 'max:' . ReviewTime::MAX_MINUTES_PER_CALL],
            'minutes.*' => ['required', 'date'],
            'surface' => ['required', Rule::in(ReviewTime::SURFACES)],
            'tab_nonce' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}$/'],
        ]);
        $session = $this->metrics->sessionFor((int) $data['course_id']);
        abort_if($session === null, 404);
        $user = $request->user();
        abort_unless((int) $session->author_id === (int) $user?->getAuthIdentifier() || $user?->can(AuthoringMetricsPermissionsEnum::READ, 'api'), 403);

        try {
            $stored = $this->time->record((int) $data['course_id'], $data['minutes'], $data['surface'], $data['tab_nonce']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['minutes' => [$e->getMessage()]]], 422);
        }

        return $this->ok(['accepted' => $stored]);
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(AuthoringMetricsPermissionsEnum::READ, 'api'), 403);
        $deleted = $this->time->deleteAll();
        foreach (\Ulams\AuthoringMetrics\Models\CourseMetric::query()->pluck('course_id') as $courseId) {
            $this->metrics->recompute((int) $courseId);
        }

        return $this->ok($deleted);
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK']);
    }
}
