<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesSessions;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\CourseBuilder\Pipeline\GenerationService;
use Ulams\CourseBuilder\Pipeline\OutlineService;
use Ulams\CourseBuilder\Pipeline\PatchService;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\CourseBuilder\Services\RunStatus;
use Ulams\CourseBuilder\Services\SessionState;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Ui\Surfaces;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * AI Course Builder REST API (prefix `/api/admin/course-builder`, `auth:api`, permission
 * `course_builder_use`). UI actions sent as runs and these endpoints share the same services, so
 * the CLI and the MCP server can drive the builder without the UI.
 *
 * @OA\Post(path="/api/admin/course-builder/sessions", summary="Start a builder session", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="title", type="string"))),
 *     @OA\Response(response=201, description="session with its state snapshot"), @OA\Response(response=429, description="daily limit reached"), @OA\Response(response=503, description="AI disabled"))
 * @OA\Get(path="/api/admin/course-builder/sessions", summary="My builder sessions", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Response(response=200, description="list"))
 * @OA\Get(path="/api/admin/course-builder/sessions/{id}", summary="A session's state snapshot", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="snapshot"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown"))
 * @OA\Delete(path="/api/admin/course-builder/sessions/{id}", summary="Delete a session (an applied course is kept)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="deleted"))
 * @OA\Post(path="/api/admin/course-builder/sessions/{id}/sources", summary="Upload a source (MD, PDF, DOCX); ingestion and the interview start by themselves", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
 *     @OA\Response(response=202, description="source and ingest run"), @OA\Response(response=422, description="rejected upload"))
 * @OA\Get(path="/api/admin/course-builder/sessions/{id}/sources/{source}", summary="A source with its section tree", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="source", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="source"))
 * @OA\Get(path="/api/admin/course-builder/fragments/{fragment}", summary="One source fragment (citation popover)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="fragment", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="fragment"))
 * @OA\Get(path="/api/admin/course-builder/sessions/{id}/brief", summary="Course Brief", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="brief"))
 * @OA\Put(path="/api/admin/course-builder/sessions/{id}/brief", summary="Edit the Course Brief", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\RequestBody(@OA\JsonContent(type="object")), @OA\Response(response=200, description="brief"), @OA\Response(response=422, description="invalid"))
 * @OA\Post(path="/api/admin/course-builder/sessions/{id}/runs", summary="Start a run from a chat message or a UI action (AG-UI RunAgentInput)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="messages", type="array", @OA\Items(type="object")), @OA\Property(property="forwardedProps", type="object"))),
 *     @OA\Response(response=202, description="run id (null when the action finished in the request)"))
 * @OA\Get(path="/api/admin/course-builder/runs/{run}", summary="Status of one run (poll it for --wait)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="run", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the run and its steps", @OA\JsonContent(
 *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", ref="#/components/schemas/CourseBuilderRunStatus"))),
 *     @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown run"))
 * @OA\Post(path="/api/admin/course-builder/runs/{run}/cancel", summary="Cancel a run", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="run", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="cancelled"))
 * @OA\Post(path="/api/admin/course-builder/runs/{run}/steps/{step}/retry", summary="Retry one failed generation step", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="run", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="step", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=202, description="queued"))
 * @OA\Get(path="/api/admin/course-builder/sessions/{id}/versions", summary="Blueprint version history", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="versions"))
 * @OA\Get(path="/api/admin/course-builder/versions/{version}", summary="A blueprint version with fragment labels", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="version"))
 * @OA\Get(path="/api/admin/course-builder/versions/{version}/diff", summary="Element-aware diff against another version (default: its parent)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="against", in="query", @OA\Schema(type="string")), @OA\Response(response=200, description="changes"))
 * @OA\Post(path="/api/admin/course-builder/versions/{version}/approve", summary="Approve a proposal (outline or patch)", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="approved"))
 * @OA\Post(path="/api/admin/course-builder/versions/{version}/reject", summary="Reject a proposal", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="rejected"))
 * @OA\Post(path="/api/admin/course-builder/versions/{version}/restore", summary="Restore an old version as a new one", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="restored"))
 * @OA\Post(path="/api/admin/course-builder/sessions/{id}/undo", summary="Undo the last content change", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="current version"))
 * @OA\Post(path="/api/admin/course-builder/sessions/{id}/redo", summary="Redo", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="current version"))
 * @OA\Post(path="/api/admin/course-builder/sessions/{id}/apply", summary="Approve the apply of the current version", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=202, description="apply run"))
 * @OA\Post(path="/api/admin/course-builder/sessions/{id}/publish", summary="Publish the applied course", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="published"))
 * @OA\Get(path="/api/admin/course-builder/sessions/{id}/usage", summary="AI calls of the session by task and model", tags={"Admin Course Builder"}, security={{"passport": {}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="usage"))
 */
class CourseBuilderController extends Controller
{
    use ResolvesSessions;

    public function __construct(
        private readonly RunService $runs,
        private readonly VersionService $versions,
        private readonly BriefService $briefs,
        private readonly SourceIngestor $ingestor,
        private readonly BlueprintApplier $applier,
        private readonly EventLog $events,
        private readonly Surfaces $surfaces,
    ) {
    }

    private static function ok(mixed $data, int $status = 200, string $message = 'OK'): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message], $status);
    }

    private static function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertCanUse($request);
        $sessions = Session::query()->where('author_id', $request->user()->getKey())->latest('updated_at')->limit(100)->get();

        return self::ok($sessions->map(fn (Session $s) => SessionState::summary($s) + ['costMicroUsd' => (int) $s->cost_micro_usd])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanUse($request);
        $request->validate(['title' => ['nullable', 'string', 'max:255']]);
        $today = Session::withTrashed()->where('author_id', $request->user()->getKey())->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= (int) config('course_builder.limits.sessions_per_author_per_day', 10)) {
            return self::fail('You started the maximum number of builder sessions for today. Continue an existing one or try tomorrow.', 429);
        }
        $session = Session::query()->create(['author_id' => $request->user()->getKey(), 'title' => $request->input('title'), 'status' => Session::DRAFT]);
        $this->events->text($session, null, 'Drop your source material (Markdown, PDF or DOCX) and I will read it first. Every lesson I write will cite the passage it came from, and nothing reaches your academy until you approve it.');

        return self::ok(SessionState::snapshot($session), 201);
    }

    public function show(Request $request, string $session): JsonResponse
    {
        return self::ok(SessionState::snapshot($this->sessionFor($request, $session)));
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        Run::query()->where('session_id', $s->id)->whereIn('status', Run::ACTIVE)->update(['status' => 'cancelled']);
        $s->delete();

        return self::ok(['id' => $s->id]);
    }

    public function upload(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        $request->validate(['file' => ['required', 'file']]);
        try {
            $source = $this->ingestor->store($s, $request->file('file'));
        } catch (UploadRejected $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]], 'reason' => $e->reason], 422);
        }
        $run = null;
        if ($source->status === 'uploaded') {
            $run = $this->runs->start($s, 'ingest', ['sourceId' => $source->id], $request->user()->getKey());
        }

        return self::ok(['source' => SessionState::sources($s)[array_search($source->id, array_column(SessionState::sources($s), 'id'), true)] ?? null, 'runId' => $run?->id], 202);
    }

    public function source(Request $request, string $session, string $source): JsonResponse
    {
        $s = $this->sessionFor($request, $session);
        $src = Source::query()->where('session_id', $s->id)->find($source) ?? throw new NotFoundHttpException('Source not found.');
        $sections = $src->fragments()->get()->map(fn (Fragment $f) => [
            'id' => $f->id, 'label' => $f->label(), 'section' => $f->section, 'level' => $f->level, 'headingPath' => $f->heading_path,
            'pageStart' => $f->page_start, 'pageEnd' => $f->page_end, 'tokens' => $f->token_estimate, 'preview' => mb_substr($f->text, 0, 200),
        ])->all();

        return self::ok(collect(SessionState::sources($s))->firstWhere('id', $src->id) + ['metadata' => $src->metadata, 'fragments' => $sections]);
    }

    public function fragment(Request $request, string $fragment): JsonResponse
    {
        $f = preg_match('/^frg_[a-z2-7]{12}$/', $fragment) ? Fragment::query()->find($fragment) : null;
        $source = $f?->source;
        if ($f === null || $source === null) {
            throw new NotFoundHttpException('Fragment not found.');
        }
        $this->sessionFor($request, $source->session_id);

        return self::ok([
            'id' => $f->id, 'label' => $f->label(), 'section' => $f->section, 'headingPath' => $f->heading_path, 'text' => $f->text,
            'pageStart' => $f->page_start, 'pageEnd' => $f->page_end, 'source' => ['id' => $source->id, 'name' => $source->original_name],
        ]);
    }

    public function brief(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);
        $brief = $this->briefs->current($s);

        return self::ok(['brief' => $brief, 'rows' => BriefService::rows($brief)]);
    }

    /** Editing the brief after the outline marks dependent stages stale (no silent re-runs). */
    public function updateBrief(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        $brief = array_replace_recursive($this->briefs->current($s), (array) $request->input('brief', $request->except('brief')));
        foreach (array_keys((array) $request->input('brief', $request->except('brief'))) as $field) {
            if ($field !== 'decidedBy') {
                $brief['decidedBy'][$field] = 'author';
            }
        }
        try {
            $this->briefs->assertValid($brief);
        } catch (InvalidArgumentException $e) {
            return self::fail($e->getMessage(), 422);
        }
        $s->brief = $brief;
        $s->brief_version++;
        $stale = in_array($s->status, [Session::OUTLINE_REVIEW, Session::GENERATING, Session::APPLY_REVIEW, Session::APPLIED], true);
        $s->putState('briefStale', $stale);
        $s->save();
        $this->events->stateDelta($s, null, [['op' => 'replace', 'path' => '/brief', 'value' => $brief], ['op' => 'replace', 'path' => '/briefRows', 'value' => BriefService::rows($brief)]]);
        if ($stale) {
            $this->events->text($s, null, 'The brief changed after the outline was made. The outline and lessons still follow the old brief; ask me to regenerate the outline when you are ready.');
        }

        return self::ok(['brief' => $brief, 'stale' => $stale]);
    }

    public function run(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        $request->validate(['messages' => ['array'], 'forwardedProps' => ['nullable', 'array']]);
        try {
            $result = $this->runs->handle($s, $request->all(), (int) $request->user()->getKey());
        } catch (BuilderException $e) {
            return self::fail($e->getMessage(), $e->status);
        }

        return self::ok(['runId' => $result['run']?->id, 'accepted' => $result['accepted'], 'message' => $result['message'] ?? null], 202);
    }

    public function runStatus(Request $request, string $run): JsonResponse
    {
        $r = (preg_match('/^[0-9a-z]{26}$/i', $run) ? Run::query()->with('steps')->find(strtolower($run)) : null)
            ?? throw new NotFoundHttpException('Run not found.');
        $this->sessionFor($request, $r->session_id, 'view');

        return self::ok(RunStatus::from($r));
    }

    public function cancel(Request $request, string $run): JsonResponse
    {
        $r = Run::query()->find($run) ?? throw new NotFoundHttpException('Run not found.');
        $this->sessionFor($request, $r->session_id, 'update');
        if ($r->isActive()) {
            $r->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
            Step::query()->where('run_id', $r->id)->whereIn('status', ['pending', 'queued'])->update(['status' => 'failed', 'error' => 'Cancelled']);
            $this->events->runError($r, 'Cancelled by the author.', 'cancelled');
        }

        return self::ok(['id' => $r->id, 'status' => $r->status]);
    }

    public function retryStep(Request $request, string $run, string $step): JsonResponse
    {
        $r = Run::query()->find($run) ?? throw new NotFoundHttpException('Run not found.');
        $s = $this->sessionFor($request, $r->session_id, 'update');
        $st = Step::query()->where('run_id', $r->id)->find($step) ?? throw new NotFoundHttpException('Step not found.');
        if ($st->status !== 'failed') {
            return self::fail('Only a failed step can be retried.', 409);
        }
        app(GenerationService::class)->retry($st);

        return self::ok(['stepId' => $st->id, 'sessionId' => $s->id], 202);
    }

    public function versions(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);

        return self::ok(['currentVersionId' => $s->current_version_id, 'appliedVersionId' => $s->applied_version_id, 'versions' => $this->versions->history($s)]);
    }

    private function versionFor(Request $request, string $version, string $ability = 'view'): array
    {
        $v = preg_match('/^[0-9a-z]{26}$/i', $version) ? Version::query()->find(strtolower($version)) : null;
        if ($v === null) {
            throw new NotFoundHttpException('Version not found.');
        }

        return [$this->sessionFor($request, $v->session_id, $ability), $v];
    }

    public function version(Request $request, string $version): JsonResponse
    {
        [, $v] = $this->versionFor($request, $version);

        return self::ok([
            'id' => $v->id, 'number' => $v->number, 'kind' => $v->kind, 'origin' => $v->origin, 'status' => $v->status, 'reason' => $v->reason,
            'parentId' => $v->parent_id, 'elementId' => $v->element_id, 'document' => $v->document, 'fragments' => Surfaces::labels($v->document),
            'createdAt' => $v->created_at?->toIso8601String(),
        ]);
    }

    public function diff(Request $request, string $version): JsonResponse
    {
        [$s, $v] = $this->versionFor($request, $version);
        $against = $request->query('against');
        $other = $against ? Version::query()->where('session_id', $s->id)->find($against) : $v->parent;

        return self::ok(['against' => $other?->id, 'changes' => BlueprintDiff::compare($other?->document ?? [], $v->document)]);
    }

    public function approve(Request $request, string $version): JsonResponse
    {
        [$s, $v] = $this->versionFor($request, $version, 'update');
        if ($v->status !== Version::PROPOSED) {
            return self::fail('This version is not waiting for a decision.', 409);
        }
        $surface = match ($v->kind) {
            'outline' => ['approve_outline', "outline-{$v->id}"],
            'patch' => ['approve_patch', "patch-{$v->id}"],
            'content' => ['approve_apply', "apply-{$v->id}"],
            default => null,
        };
        if ($surface === null) {
            return self::fail('This version cannot be approved.', 409);
        }

        return $this->decide($request, $s, $surface[0], $surface[1], ['versionId' => $v->id, 'edits' => (array) $request->input('edits', [])]);
    }

    public function reject(Request $request, string $version): JsonResponse
    {
        [$s, $v] = $this->versionFor($request, $version, 'update');
        if ($v->status !== Version::PROPOSED || !in_array($v->kind, ['outline', 'patch'], true)) {
            return self::fail('This version is not waiting for a decision.', 409);
        }

        return $this->decide($request, $s, $v->kind === 'outline' ? 'reject_outline' : 'reject_patch', "{$v->kind}-{$v->id}", ['versionId' => $v->id, 'comment' => (string) $request->input('comment', '')]);
    }

    private function decide(Request $request, Session $s, string $action, string $surfaceId, array $context): JsonResponse
    {
        try {
            $result = $this->runs->action($s, ['name' => $action, 'surfaceId' => $surfaceId, 'context' => $context], (int) $request->user()->getKey());
        } catch (BuilderException $e) {
            return self::fail($e->getMessage(), $e->status);
        }
        if (!$result['accepted']) {
            return self::fail($result['message'] ?? 'Not accepted.', 409);
        }

        return self::ok(['runId' => $result['run']?->id, 'state' => SessionState::snapshot($s->refresh())]);
    }

    public function restore(Request $request, string $version): JsonResponse
    {
        [$s, $v] = $this->versionFor($request, $version, 'update');
        try {
            $new = $this->versions->restore($s, $v, (int) $request->user()->getKey());
        } catch (InvalidArgumentException $e) {
            return self::fail($e->getMessage(), 409);
        }

        return $this->afterMove($request, $s, $new, "Restored version {$v->number} as version {$new->number}.");
    }

    public function undo(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        try {
            $v = $this->versions->undo($s);
        } catch (InvalidArgumentException $e) {
            return self::fail($e->getMessage(), 409);
        }

        return $this->afterMove($request, $s, $v, "Undone: the course is back to version {$v->number}.");
    }

    public function redo(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        try {
            $v = $this->versions->redo($s);
        } catch (InvalidArgumentException $e) {
            return self::fail($e->getMessage(), 409);
        }

        return $this->afterMove($request, $s, $v, "Redone: version {$v->number} is current again.");
    }

    private function afterMove(Request $request, Session $s, Version $v, string $message): JsonResponse
    {
        $this->events->text($s, null, $message);
        $this->events->stateDelta($s, null, [['op' => 'replace', 'path' => '/session', 'value' => SessionState::summary($s)]]);
        $run = $this->runs->reapply($s, (int) $request->user()->getKey());
        if ($run === null && $s->status === Session::APPLY_REVIEW) {
            $this->surfaces->apply($s, null, $v, $this->applier->plan($s, $v->document));
        }

        return self::ok(['currentVersionId' => $v->id, 'runId' => $run?->id, 'state' => SessionState::snapshot($s->refresh())]);
    }

    public function apply(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        $v = $s->currentVersion;
        if ($v === null || !in_array($v->kind, VersionService::CONTENT_KINDS, true)) {
            return self::fail('There is no generated content to apply yet.', 409);
        }
        $this->surfaces->apply($s, null, $v, $this->applier->plan($s, $v->document), 'applying');
        $run = $this->runs->start($s, 'apply', ['versionId' => $v->id], (int) $request->user()->getKey());

        return self::ok(['runId' => $run->id], 202);
    }

    public function publish(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        if ($s->course_id === null) {
            return self::fail('Apply the course before publishing it.', 409);
        }
        $this->applier->publish($s, $request->user());
        $s->putState('published', true);
        $s->save();
        $this->events->custom($s, null, 'published', ['courseId' => $s->course_id]);
        $this->events->text($s, null, 'The course is published. Learners can enrol now.');

        return self::ok(['courseId' => $s->course_id, 'published' => true]);
    }

    public function usage(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);
        $rows = AiCall::query()->forSubject(Session::SUBJECT_TYPE, $s->id)
            ->selectRaw('task, profile, COALESCE(model_served, model_requested) AS model, COUNT(*) AS calls, SUM(input_tokens) AS input_tokens, SUM(output_tokens) AS output_tokens, SUM(cache_read_tokens) AS cache_read_tokens, SUM(cache_creation_tokens) AS cache_creation_tokens, SUM(cost_micro_usd) AS cost_micro_usd, SUM(latency_ms) AS latency_ms')
            ->groupBy('task', 'profile', DB::raw('COALESCE(model_served, model_requested)'))
            ->orderBy('task')->get();
        $labels = fn (?string $profile) => (string) config("ai.profiles.{$profile}.label", (string) $profile);

        return self::ok([
            'total' => \Ulams\CourseBuilder\Pipeline\Llm::cost($s),
            // the profile label is shown; the model id stays in the API response for admins and logs
            'byTask' => $rows->map(fn ($r) => [
                'task' => $r->task, 'profile' => $r->profile, 'profileLabel' => $labels($r->profile), 'model' => $r->model, 'calls' => (int) $r->calls,
                'inputTokens' => (int) $r->input_tokens, 'outputTokens' => (int) $r->output_tokens, 'cacheReadTokens' => (int) $r->cache_read_tokens,
                'cacheCreationTokens' => (int) $r->cache_creation_tokens, 'costMicroUsd' => (int) $r->cost_micro_usd, 'latencyMs' => (int) $r->latency_ms,
            ])->all(),
        ]);
    }
}
