<?php

namespace Ulams\LearnerInsights\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Enums\LearnerInsightsPermissionsEnum as Permission;
use Ulams\LearnerInsights\Http\Controllers\Swagger\AnalyticsSwagger;
use Ulams\LearnerInsights\Jobs\BackfillJob;
use Ulams\LearnerInsights\Services\Analytics;

/** Analytics for authors and admins. Aggregates only, except the two individual endpoints, which need their own permission. */
class AnalyticsController extends Controller implements AnalyticsSwagger
{
    public function __construct(private readonly Analytics $analytics)
    {
    }

    public function summary(Request $request, int $course): JsonResponse
    {
        $this->authorizeView($request, Course::query()->findOrFail($course));

        return response()->json(['data' => $this->analytics->summary($course, $this->period($request))]);
    }

    public function elements(Request $request, int $course): JsonResponse
    {
        $this->authorizeView($request, Course::query()->findOrFail($course));
        $sort = $request->validate(['sort' => ['sometimes', Rule::in(['struggle', 'active', 'recovery'])]])['sort'] ?? 'struggle';

        return response()->json(['data' => $this->analytics->elements($course, $this->period($request), $sort)]);
    }

    public function learners(Request $request, int $course): JsonResponse
    {
        $model = Course::query()->findOrFail($course);
        $this->authorizeIndividuals($request, $model);
        $status = $request->validate(['status' => ['sometimes', Rule::in(['struggling', 'at_risk', 'on_track'])]])['status'] ?? null;

        return response()->json(['data' => $this->analytics->learners($course, $status)]);
    }

    public function timeline(Request $request, int $user, int $course): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::LEARNER_INSIGHTS_VIEW_INDIVIDUALS, 'api'), 403);
        Course::query()->findOrFail($course);
        \Ulams\Core\Models\User::query()->findOrFail($user);

        return response()->json(['data' => $this->analytics->timeline($user, $course)]);
    }

    public function backfill(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::LEARNER_INSIGHTS_MANAGE, 'api'), 403);
        $courseId = $request->validate(['courseId' => ['sometimes', 'integer', 'min:1']])['courseId'] ?? null;
        if ($courseId !== null) {
            Course::query()->findOrFail($courseId);
        }
        BackfillJob::dispatch($courseId);

        return response()->json(['data' => ['queued' => true]], 202);
    }

    private function period(Request $request): int
    {
        return (int) ($request->validate(['period' => ['sometimes', 'integer', Rule::in(Analytics::PERIODS)]])['period'] ?? 30);
    }

    /** Admins with the permission, and the authors of the course. */
    private function authorizeView(Request $request, Course $course): void
    {
        $user = $request->user();
        abort_unless($user && ($user->can(Permission::LEARNER_INSIGHTS_VIEW, 'api') || $course->hasAuthor($user)), 403);
    }

    /** The permission, or an author of the course when the tenant allows instructors to see individuals. */
    private function authorizeIndividuals(Request $request, Course $course): void
    {
        $user = $request->user();
        $allowed = $user && ($user->can(Permission::LEARNER_INSIGHTS_VIEW_INDIVIDUALS, 'api') || (config('learner_insights.instructors_see_individuals') && $course->hasAuthor($user)));
        abort_unless($allowed, 403);
    }
}
