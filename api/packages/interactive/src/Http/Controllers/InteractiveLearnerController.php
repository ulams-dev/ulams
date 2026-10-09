<?php

namespace Ulams\Interactive\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Ulams\Courses\Models\Topic;
use Ulams\Interactive\Http\Requests\InteractiveEventsRequest;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\Interactive\Services\InteractiveProgressService;

/**
 * Learners play an Interactive topic from the tenant content origin (ADR 0086). The frame never gets a
 * token: the lesson page forwards the bridge's events through the front BFF with the learner's session.
 *
 * @OA\Post(path="/api/interactive/launches/{topic}", summary="Start an Interactive topic", tags={"Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="{url, version, manifest, topic}; url is the entry file on the content origin"),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="no access to the course"),
 *     @OA\Response(response=404, description="not an Interactive topic, or the type is switched off"), @OA\Response(response=503, description="no content origin"))
 * @OA\Post(path="/api/interactive/topics/{topic}/events", summary="Progress events from the lesson page", tags={"Interactive"}, security={{"passport": {}}},
 *     description="A batch of at most 40 bridge events (stepChanged, progress, complete, score, event) for the logged-in learner. Completion follows the topic's completion rule.",
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\JsonContent(required={"events"}, @OA\Property(property="events", type="array", @OA\Items(type="object")))),
 *     @OA\Response(response=200, description="{status, progress}; status 1 means the topic is complete"),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="no access to the course"),
 *     @OA\Response(response=404, description="not an Interactive topic"), @OA\Response(response=422, description="invalid batch"), @OA\Response(response=429, description="too many requests"))
 */
class InteractiveLearnerController extends Controller
{
    public function __construct(private readonly InteractiveProgressService $progress)
    {
    }

    public function launch(Request $request, int $topic): JsonResponse
    {
        abort_unless(config('ulams_interactive.enabled'), 404);
        /** @var Topic|null $model */
        $model = Topic::query()->with(['topicable', 'lesson.course'])->find($topic);
        if ($model === null || !$model->topicable instanceof InteractiveTopic) {
            return response()->json(['success' => false, 'message' => 'Not an Interactive topic'], 404);
        }
        $course = $model->lesson?->course;
        if ($course === null || !Gate::forUser($request->user())->allows('attend', $course)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $origin = rtrim(trim((string) (config('ulams_uploads.content_origin') ?: config('scorm.content_origin'))), '/');
        if ($origin === '') {
            return response()->json(['success' => false, 'message' => 'Interactive lessons cannot be played here yet (no content origin).'], 503);
        }

        /** @var InteractiveTopic $content */
        $content = $model->topicable;
        $version = $content->resolveVersion();
        if ($version === null) {
            return response()->json(['success' => false, 'message' => 'The package is missing.'], 404);
        }
        $version->setRelation('package', $content->package);
        $manifest = $version->manifest;
        $directory = $content->package->directory($version->version);

        $this->progress->open($model, $request->user());

        return response()->json(['success' => true, 'data' => [
            'url' => $origin . '/' . $version->entryPath(),
            'version' => $version->version,
            'manifest' => [
                'title' => $manifest['title'],
                'steps' => array_map(fn (array $s) => array_filter([
                    'id' => $s['id'],
                    'title' => $s['title'],
                    'text' => $s['text'],
                    'poster' => isset($s['poster']) ? $origin . '/' . $directory . '/' . $s['poster'] : null,
                ], fn ($v) => $v !== null), $manifest['steps']),
                'capabilities' => (object) ($manifest['capabilities'] ?? []),
                'requires' => $manifest['requires'] ?? [],
                'locales' => $manifest['locales'],
                'defaultLocale' => $manifest['defaultLocale'],
                'licence' => $manifest['licence'],
                'attribution' => $manifest['attribution'] ?? null,
                'source' => $manifest['source'] ?? null,
                'a11y' => $manifest['a11y'] ?? null,
            ],
            'topic' => [
                'start_step' => $content->start_step,
                'end_step' => $content->end_step,
                'completion_rule' => $content->completion_rule,
                'pass_score' => $content->pass_score,
                'display' => $content->display,
                'height' => $content->height,
                'text' => $content->text,
            ],
        ], 'message' => 'OK']);
    }

    public function events(InteractiveEventsRequest $request, int $topic): JsonResponse
    {
        $result = $this->progress->apply($request->topic(), $request->user(), $request->events());

        return response()->json(['success' => true, 'data' => $result, 'message' => 'OK']);
    }
}
