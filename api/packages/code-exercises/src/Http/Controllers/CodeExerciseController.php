<?php

namespace Ulams\CodeExercises\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Ulams\CodeExercises\Http\Requests\AttemptRequest;
use Ulams\CodeExercises\Http\Resources\CodeExerciseAttemptResource;
use Ulams\CodeExercises\Models\CodeExerciseAttempt;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\CodeExercises\Services\AttemptService;
use Ulams\Courses\Models\Topic;

/**
 * Code exercises (ADR 0170). The exercise runs in the browser sandbox on the tenant content origin; this
 * API hands the page the exercise and the runner's address, and records the results the page reports for
 * the learner's own session (no token ever reaches the frame).
 *
 * @OA\Post(path="/api/code-exercises/launches/{topic}", operationId="code-exercises.launches.by-topic.post", summary="Open a code exercise", tags={"Code exercises"}, security={{"passport": {}}},
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="{runner: {url, version}, exercise, attempts}; runner.url is the sandboxed runner page on the content origin", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="no access to the course"),
 *     @OA\Response(response=404, description="not a code exercise, or the type is switched off"), @OA\Response(response=503, description="no content origin"))
 * @OA\Post(path="/api/code-exercises/topics/{topic}/attempts", operationId="code-exercises.topics.by-topic.attempts.post", summary="Report the result of a run", tags={"Code exercises"}, security={{"passport": {}}},
 *     description="The learner's browser ran the visible tests and reports each test's result. The score is recomputed here and the attempt is stored as a practice attempt (verified = false): it moves the topic's progress by its completion rule and records a learner signal, and never counts for certificates or compliance.",
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\JsonContent(required={"results"},
 *         @OA\Property(property="results", type="array", @OA\Items(type="object", required={"name","status"}, @OA\Property(property="name", type="string"), @OA\Property(property="status", type="string", enum={"pass","fail","error"}), @OA\Property(property="message", type="string", nullable=true))),
 *         @OA\Property(property="files_hash", type="string", nullable=true, description="sha256 of the submitted files"),
 *         @OA\Property(property="runner", type="string", nullable=true), @OA\Property(property="duration_ms", type="integer", nullable=true))),
 *     @OA\Response(response=201, description="{attempt, status}; status 1 means the topic is complete", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="no access to the course"),
 *     @OA\Response(response=404, description="not a code exercise"), @OA\Response(response=422, description="invalid result"), @OA\Response(response=429, description="too many requests"))
 * @OA\Get(path="/api/code-exercises/topics/{topic}/attempts", operationId="code-exercises.topics.by-topic.attempts.get", summary="My attempts at a code exercise", tags={"Code exercises"}, security={{"passport": {}}},
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="The caller's own attempts, newest first", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="no access to the course"), @OA\Response(response=404, description="not a code exercise"))
 * @OA\Get(path="/api/admin/code-exercises/topics/{topic}/preview", operationId="admin.code-exercises.topics.by-topic.preview.get", summary="Preview a code exercise as its author", tags={"Code exercises"}, security={{"passport": {}}},
 *     description="The same answer as a launch, for a user who may edit the course. Nothing is tracked.",
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="{runner, exercise}", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="not allowed to edit the course"),
 *     @OA\Response(response=404, description="not a code exercise"), @OA\Response(response=503, description="no content origin"))
 * @OA\Get(path="/api/admin/code-exercises/topics/{topic}/attempts", operationId="admin.code-exercises.topics.by-topic.attempts.get", summary="Attempts at a code exercise (teacher report)", tags={"Code exercises"}, security={{"passport": {}}},
 *     description="Every learner's attempts at the exercise, newest first, for a user who may edit the course. Practice attempts are marked verified = false.",
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", maximum=100)),
 *     @OA\Response(response=200, description="A page of attempts with the learner's id and name", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="not allowed to edit the course"), @OA\Response(response=404, description="not a code exercise"))
 */
class CodeExerciseController extends Controller
{
    public function __construct(private readonly AttemptService $attempts)
    {
    }

    public function launch(Request $request, int $topic): JsonResponse
    {
        $model = $this->exercise($topic);
        $course = $model->lesson?->course;
        if ($course === null || !Gate::forUser($request->user())->allows('attend', $course)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $payload = $this->payload($model);
        if ($payload === null) {
            return response()->json(['success' => false, 'message' => 'Code exercises cannot be run here yet (no content origin).'], 503);
        }

        $this->attempts->open($model, $request->user());
        $summary = $this->attempts->summary($model, $request->user());
        $payload['attempts'] = [
            'count' => $summary['count'],
            'best_score' => $summary['best_score'],
            'last' => $summary['last'] === null ? null : new CodeExerciseAttemptResource($summary['last']),
        ];

        return response()->json(['success' => true, 'data' => $payload, 'message' => 'OK']);
    }

    public function preview(Request $request, int $topic): JsonResponse
    {
        $model = $this->exercise($topic);
        if (!Gate::forUser($request->user())->allows('update', $model)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $payload = $this->payload($model);
        if ($payload === null) {
            return response()->json(['success' => false, 'message' => 'Code exercises cannot be run here yet (no content origin).'], 503);
        }

        return response()->json(['success' => true, 'data' => $payload, 'message' => 'OK']);
    }

    public function store(AttemptRequest $request, int $topic): JsonResponse
    {
        $result = $this->attempts->record($request->topic(), $request->exercise(), $request->user(), $request->results(), $request->meta());

        return response()->json([
            'success' => true,
            'data' => ['attempt' => new CodeExerciseAttemptResource($result['attempt']), 'status' => $result['status']],
            'message' => 'OK',
        ], 201);
    }

    public function mine(Request $request, int $topic): JsonResponse
    {
        $model = $this->exercise($topic);
        $course = $model->lesson?->course;
        if ($course === null || !Gate::forUser($request->user())->allows('attend', $course)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $rows = CodeExerciseAttempt::query()->where(['user_id' => $request->user()->getKey(), 'topic_id' => $model->getKey()])->orderByDesc('attempt_no')->limit(100)->get();

        return response()->json(['success' => true, 'data' => CodeExerciseAttemptResource::collection($rows), 'message' => 'OK']);
    }

    public function report(Request $request, int $topic): JsonResponse
    {
        $model = $this->exercise($topic);
        if (!Gate::forUser($request->user())->allows('update', $model)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $page = CodeExerciseAttempt::query()->where('topic_id', $model->getKey())->orderByDesc('id')->paginate($perPage);
        $users = config('auth.providers.users.model')::query()->whereIn('id', $page->pluck('user_id')->unique())->get()->keyBy('id');

        $data = $page->getCollection()->map(fn (CodeExerciseAttempt $a) => (new CodeExerciseAttemptResource($a))->toArray($request) + [
            'user' => ['id' => $a->user_id, 'name' => trim(($users[$a->user_id]->first_name ?? '') . ' ' . ($users[$a->user_id]->last_name ?? ''))],
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'message' => 'OK',
        ]);
    }

    /** The topic when it is a code exercise on a tenant that has the type; 404 otherwise. */
    private function exercise(int $topic): Topic
    {
        abort_unless(config('ulams_code_exercises.enabled'), 404);
        /** @var Topic|null $model */
        $model = Topic::query()->with(['topicable', 'lesson.course'])->find($topic);
        abort_unless($model !== null && $model->topicable instanceof CodeExerciseTopic, 404);

        return $model;
    }

    /** @return array<string, mixed>|null null without a content origin */
    private function payload(Topic $topic): ?array
    {
        $origin = rtrim(trim((string) (config('ulams_uploads.content_origin') ?: config('scorm.content_origin'))), '/');
        if ($origin === '') {
            return null;
        }
        $version = (int) config('ulams_code_exercises.runner.version');
        /** @var CodeExerciseTopic $exercise */
        $exercise = $topic->topicable;

        return [
            'runner' => ['url' => "{$origin}/runtime/code-runner/{$version}/runner.html", 'version' => $version],
            'exercise' => $exercise->runnerPayload() + [
                'completion_rule' => $exercise->completion_rule,
                'pass_score' => $exercise->pass_score,
                'text' => $exercise->text,
            ],
        ];
    }
}
