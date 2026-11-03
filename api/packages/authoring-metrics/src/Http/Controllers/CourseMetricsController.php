<?php

namespace Ulams\AuthoringMetrics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Ulams\AuthoringMetrics\Enums\AuthoringMetricsPermissionsEnum;
use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\AuthoringMetrics\Services\CourseMetrics;

/**
 * How long a course took to produce and what it cost, from recorded runs and calls (ADR 0194, plan
 * docs/plans/authoring-metric-and-report-builder.md). The figures are the tenant's own measurements.
 *
 * @OA\Get(path="/api/admin/authoring-metrics/courses", operationId="admin.authoring-metrics.courses.list", summary="Authoring metric of courses", tags={"Authoring metrics"}, security={{"passport": {}}},
 *     description="One snapshot per course built with the Course Builder: generation, AI edit, interview and model time in seconds, build and maintenance cost in micro USD, the number of model calls and failed calls, element counts and how many AI elements were accepted unchanged, revised on request or edited by an author. Author time is null until review time is recorded. Needs the permission authoring_metrics_read.",
 *     @OA\Parameter(name="from", in="query", description="Courses created on or after this date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="Courses created on or before this date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="course_id[]", in="query", description="Only these courses", @OA\Schema(type="array", @OA\Items(type="integer"))),
 *     @OA\Response(response=200, description="List of course snapshots", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 *
 * @OA\Get(path="/api/admin/authoring-metrics/courses/{course}", operationId="admin.authoring-metrics.courses.by-id.get", summary="Authoring metric of one course", tags={"Authoring metrics"}, security={{"passport": {}}},
 *     description="The snapshot of one course with the time of every pipeline stage (outline, lessons, quizzes, apply and the rest, each marked exact, coarse or incomplete) and the split of its AI elements. Needs authoring_metrics_read, or being the author of the course's builder session. 404 when the course was not built with the Course Builder.",
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Snapshot, stages and elements", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Not built with the Course Builder"))
 */
class CourseMetricsController extends Controller
{
    public function __construct(private readonly CourseMetrics $metrics)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canReadAll($request), 403);
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'course_id' => ['sometimes', 'array', 'max:200'],
            'course_id.*' => ['integer'],
        ]);
        $query = CourseMetric::query()->orderBy('course_id');
        if (!empty($data['course_id'])) {
            $query->whereIn('course_id', $data['course_id']);
        }
        if (isset($data['from']) || isset($data['to'])) {
            $courses = DB::table('courses')->select('id');
            isset($data['from']) && $courses->where('created_at', '>=', $data['from'] . ' 00:00:00');
            isset($data['to']) && $courses->where('created_at', '<=', $data['to'] . ' 23:59:59');
            $query->whereIn('course_id', $courses);
        }

        return $this->ok($query->get()->map(fn (CourseMetric $m) => $this->snapshot($m))->all());
    }

    public function show(Request $request, int $course): JsonResponse
    {
        $session = $this->metrics->sessionFor($course);
        abort_if($session === null, 404);
        abort_unless($this->canReadAll($request) || (int) $session->author_id === (int) $request->user()?->getAuthIdentifier(), 403);
        $metric = $this->metrics->snapshot($course);
        abort_if($metric === null, 404);

        $stages = StageTiming::query()->where('session_id', $session->id)->orderBy('started_at')->get()->map(fn (StageTiming $t) => [
            'run_id' => $t->run_id, 'run_kind' => $t->run_kind, 'stage' => $t->stage, 'started_at' => $t->started_at->toIso8601String(),
            'finished_at' => $t->finished_at?->toIso8601String(), 'seconds' => $t->seconds === null ? null : (float) $t->seconds, 'quality' => $t->quality,
        ])->all();

        return $this->ok($this->snapshot($metric) + ['stages' => $stages]);
    }

    private function canReadAll(Request $request): bool
    {
        return (bool) $request->user()?->can(AuthoringMetricsPermissionsEnum::READ, 'api');
    }

    /** @return array<string,mixed> */
    private function snapshot(CourseMetric $m): array
    {
        return [
            'course_id' => (int) $m->course_id,
            'session_id' => $m->session_id,
            'method_version' => (int) $m->method_version,
            'computed_at' => $m->computed_at->toIso8601String(),
            'time' => [
                'generation_seconds' => (float) $m->generation_seconds,
                'ai_edit_seconds' => (float) $m->ai_edit_seconds,
                'interview_seconds' => (float) $m->interview_seconds,
                'model_seconds' => (float) $m->model_seconds,
                // review time is opt-in and not recorded yet: never a made-up zero
                'author_minutes' => $m->author_minutes === null ? null : (float) $m->author_minutes,
            ],
            'cost' => [
                'build_micro_usd' => (int) $m->cost_build_micro_usd,
                'maintenance_micro_usd' => (int) $m->cost_maintenance_micro_usd,
                'llm_calls' => (int) $m->llm_calls,
                'llm_calls_failed' => (int) $m->llm_calls_failed,
            ],
            'counts' => [
                'lessons' => (int) $m->lessons, 'questions' => (int) $m->questions, 'blocks' => (int) $m->blocks, 'cited_blocks' => (int) $m->cited_blocks,
                'learner_minutes_estimate' => (int) $m->learner_minutes_estimate,
            ],
            'elements' => [
                'ai_elements' => (int) $m->ai_elements,
                'accepted_unchanged' => (int) $m->ai_accepted_unchanged,
                'revised_on_request' => (int) $m->ai_revised_on_request,
                'edited_by_author' => (int) $m->ai_edited_by_author,
                'unknown' => (int) $m->ai_unknown,
                'author_written' => (int) $m->author_elements,
            ],
        ];
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK']);
    }
}
