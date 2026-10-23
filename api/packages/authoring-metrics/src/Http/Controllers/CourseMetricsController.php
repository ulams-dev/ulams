<?php

namespace Ulams\AuthoringMetrics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ulams\AuthoringMetrics\Enums\AuthoringMetricsPermissionsEnum;
use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Models\StageTiming;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Services\HoursPerLearnerHour;

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
 * @OA\Get(path="/api/admin/authoring-metrics/courses/export", operationId="admin.authoring-metrics.courses.export", summary="Export the authoring metric", tags={"Authoring metrics"}, security={{"passport": {}}},
 *     description="Download the snapshots as CSV or JSON lines, one row per course, with the same filters as the list. Every row carries method_version so the file says which definitions produced the numbers. Needs authoring_metrics_read.",
 *     @OA\Parameter(name="format", in="query", description="csv (default) or jsonl", @OA\Schema(type="string", enum={"csv","jsonl"})),
 *     @OA\Parameter(name="from", in="query", description="Courses created on or after this date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="Courses created on or before this date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="course_id[]", in="query", description="Only these courses", @OA\Schema(type="array", @OA\Items(type="integer"))),
 *     @OA\Response(response=200, description="The file", @OA\MediaType(mediaType="text/csv", @OA\Schema(type="string", format="binary"))), @OA\Response(response=403, description="Forbidden"))
 *
 * @OA\Get(path="/api/admin/authoring-metrics/summary", operationId="admin.authoring-metrics.summary", summary="Authoring metric of the academy", tags={"Authoring metrics"}, security={{"passport": {}}},
 *     description="The academy's median hours of work per hour of learner time over the courses that have the figure, with how many (n). Needs recorded author time, and at least three courses, otherwise there is no median and status says why (author_time_not_recorded, not_enough_courses). Also the academy's own earlier figure when it entered one (labelled as its own estimate) and the maintenance cost, separately. The figure is the academy's own: no industry ratio is ever returned. Needs authoring_metrics_read.",
 *     @OA\Parameter(name="from", in="query", description="Courses created on or after this date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="Courses created on or before this date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="status, n_courses, n_with_figure, median_hours_per_learner_hour, own_baseline, maintenance, formula", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
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
        $rows = $this->filtered($request)->get();
        $titles = DB::table('courses')->whereIn('id', $rows->pluck('course_id'))->pluck('title', 'id');

        return $this->ok($rows->map(fn (CourseMetric $m) => $this->snapshot($m, $titles[$m->course_id] ?? null))->all());
    }

    public function summary(Request $request): JsonResponse
    {
        $rows = $this->filtered($request)->get();
        $baseline = config('authoring_metrics.baseline_hours_per_learner_hour');
        $maintenance = $rows->filter(fn (CourseMetric $m) => (int) $m->cost_maintenance_micro_usd > 0);

        return $this->ok(HoursPerLearnerHour::summarise($rows) + [
            'own_baseline' => is_numeric($baseline) && (float) $baseline > 0 ? ['hours_per_learner_hour' => (float) $baseline, 'label' => 'own_estimate'] : null,
            'maintenance' => ['courses' => $maintenance->count(), 'cost_micro_usd' => (int) $maintenance->sum('cost_maintenance_micro_usd')],
            'formula' => HoursPerLearnerHour::FORMULA,
            'method_version' => CourseMetrics::METHOD_VERSION,
        ]);
    }

    /** CSV or JSON lines of the same rows; the file is the tenant's own data, so it carries no more than the list does. */
    public function export(Request $request): StreamedResponse
    {
        $format = $request->validate(['format' => ['sometimes', 'in:csv,jsonl']])['format'] ?? 'csv';
        $rows = $this->filtered($request)->get()->map(fn (CourseMetric $m) => $this->flat($m))->all();
        $name = 'authoring-metrics-' . now()->format('Ymd') . '.' . $format;

        return response()->streamDownload(function () use ($rows, $format) {
            $out = fopen('php://output', 'w');
            if ($format === 'jsonl') {
                foreach ($rows as $row) {
                    fwrite($out, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                }
            } else {
                fputcsv($out, array_keys($rows[0] ?? self::flatColumns()), ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($v) => $v === null ? '' : $v, array_values($row)), ',', '"', '');
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => $format === 'jsonl' ? 'application/x-ndjson' : 'text/csv; charset=UTF-8']);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<CourseMetric> */
    private function filtered(Request $request)
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

        return $query;
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

        return $this->ok($this->snapshot($metric, DB::table('courses')->where('id', $course)->value('title')) + ['stages' => $stages]);
    }

    /**
     * One flat row per course for the files. Only numbers, identifiers and dates: nothing a person typed, so a
     * spreadsheet has no cell that could start with = + - @ and be read as a formula.
     *
     * @return array<string,string|int|float|null>
     */
    private function flat(CourseMetric $m): array
    {
        $s = $this->snapshot($m); // the course title is in the screens, not in the files

        return [
            'course_id' => $s['course_id'], 'session_id' => $s['session_id'], 'method_version' => $s['method_version'], 'computed_at' => $s['computed_at'],
            'generation_seconds' => $s['time']['generation_seconds'], 'ai_edit_seconds' => $s['time']['ai_edit_seconds'],
            'interview_seconds' => $s['time']['interview_seconds'], 'model_seconds' => $s['time']['model_seconds'], 'author_minutes' => $s['time']['author_minutes'],
            'cost_build_micro_usd' => $s['cost']['build_micro_usd'], 'cost_maintenance_micro_usd' => $s['cost']['maintenance_micro_usd'],
            'llm_calls' => $s['cost']['llm_calls'], 'llm_calls_failed' => $s['cost']['llm_calls_failed'],
            'lessons' => $s['counts']['lessons'], 'questions' => $s['counts']['questions'], 'blocks' => $s['counts']['blocks'], 'cited_blocks' => $s['counts']['cited_blocks'],
            'learner_minutes_estimate' => $s['counts']['learner_minutes_estimate'], 'hours_per_learner_hour' => $s['hours']['hours_per_learner_hour'],
            'ai_elements' => $s['elements']['ai_elements'], 'ai_accepted_unchanged' => $s['elements']['accepted_unchanged'],
            'ai_revised_on_request' => $s['elements']['revised_on_request'], 'ai_edited_by_author' => $s['elements']['edited_by_author'],
            'ai_unknown' => $s['elements']['unknown'], 'author_elements' => $s['elements']['author_written'],
        ];
    }

    /** @return array<string,null> */
    private static function flatColumns(): array
    {
        return array_fill_keys(['course_id', 'session_id', 'method_version', 'computed_at', 'generation_seconds', 'ai_edit_seconds', 'interview_seconds', 'model_seconds', 'author_minutes',
            'cost_build_micro_usd', 'cost_maintenance_micro_usd', 'llm_calls', 'llm_calls_failed', 'lessons', 'questions', 'blocks', 'cited_blocks', 'learner_minutes_estimate', 'hours_per_learner_hour',
            'ai_elements', 'ai_accepted_unchanged', 'ai_revised_on_request', 'ai_edited_by_author', 'ai_unknown', 'author_elements'], null);
    }

    private function canReadAll(Request $request): bool
    {
        return (bool) $request->user()?->can(AuthoringMetricsPermissionsEnum::READ, 'api');
    }

    /** @return array<string,mixed> */
    private function snapshot(CourseMetric $m, ?string $title = null): array
    {
        return [
            'course_id' => (int) $m->course_id,
            'course_title' => $title,
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
            // from the columns above; null, with the reason, unless author time was recorded
            'hours' => HoursPerLearnerHour::forMetric($m),
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
