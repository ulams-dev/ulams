<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\CourseAsCode\ApprovalGuard;
use Ulams\CourseBuilder\CourseAsCode\CourseAdopter;
use Ulams\CourseBuilder\CourseAsCode\CourseAsCodeException;
use Ulams\CourseBuilder\CourseAsCode\CourseExporter;
use Ulams\CourseBuilder\CourseAsCode\CourseImporter;
use Ulams\CourseBuilder\CourseAsCode\DriftAbsorber;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesSessions;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\Courses\Models\Course;

/**
 * Course-as-code API (ADR 0160, prefix `/api/admin/course-as-code`, `auth:api`, permission
 * `course_builder_use`). It works with AI off: there is no AI gate on this route group. A push is
 * always a proposed version; a person approves it (agent tokens and MCP clients never can).
 *
 * @OA\Get(path="/api/admin/course-as-code/courses/{course}/blueprint", operationId="admin.course-as-code.courses.by-course.blueprint.get", summary="Export a course as a Blueprint v3 document (current version, drift, applied version)", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the blueprint with versionId, appliedVersionId and drift", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="another author's course"), @OA\Response(response=404, description="unknown course"), @OA\Response(response=409, description="NOT_ADOPTED (no builder session) or NO_BLUEPRINT"))
 * @OA\Post(path="/api/admin/course-as-code/courses/{course}/adopt", operationId="admin.course-as-code.courses.by-course.adopt.post", summary="Adopt a course made by hand: a first blueprint, version, session and entity map from the live course (idempotent)", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=201, description="adopted: sessionId, versionId and the topics that were left out (skipped, with the reason)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=200, description="already adopted: the existing session", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="no access to the course builder, or the course may not be changed by this user"), @OA\Response(response=404, description="unknown course"),
 *     @OA\Response(response=422, description="NOTHING_TO_ADOPT (no lesson the blueprint can hold) or ADOPTION_INVALID"))
 * @OA\Post(path="/api/admin/course-as-code/courses", operationId="admin.course-as-code.courses.post", summary="Create an empty course (an approved empty blueprint) for a folder that has no course yet; the course row is made by the first apply", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"title"}, @OA\Property(property="title", type="string"), @OA\Property(property="language", type="string", example="en"), @OA\Property(property="key", type="string", description="idempotency key: the same key returns the same course (200, existing true) instead of creating another"))),
 *     @OA\Response(response=200, description="the course of this key already exists: its sessionId, current versionId and courseId", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=201, description="sessionId, versionId and versionNumber to import on top of", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="no access to the course builder"), @OA\Response(response=422, description="a missing or invalid field"))
 * @OA\Get(path="/api/admin/course-as-code/sessions/{session}", operationId="admin.course-as-code.sessions.by-session.get", summary="Where a session stands: course id, current and applied version (poll it while an apply runs)", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="courseId (null until the first apply), currentVersionId, appliedVersionId", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown session"))
 * @OA\Post(path="/api/admin/course-as-code/sessions/{session}/imports", operationId="admin.course-as-code.sessions.by-session.imports.post", summary="Import a Blueprint v3 document as a proposed version of kind import", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"document","baseVersionId"}, @OA\Property(property="document", type="object"), @OA\Property(property="baseVersionId", type="string"), @OA\Property(property="message", type="string"), @OA\Property(property="allowPending", type="boolean", description="store the import although an open update proposal edits the same elements"))),
 *     @OA\Response(response=201, description="proposed version, element diff and warnings", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=200, description="unchanged: the document equals the current version, no version is created", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown session"),
 *     @OA\Response(response=409, description="BASE_OUTDATED (with the remote diff), PROPOSAL_PENDING (an open update proposal edits the same elements) or NO_BLUEPRINT"), @OA\Response(response=413, description="document too large"), @OA\Response(response=422, description="INVALID_BLUEPRINT, BASE_UNKNOWN or a missing field"))
 * @OA\Get(path="/api/admin/course-as-code/versions/{version}", operationId="admin.course-as-code.versions.by-version.get", summary="One version with its Blueprint v3 document (what a three-way merge needs as the base)", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="the version and its document", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown version"))
 * @OA\Post(path="/api/admin/course-as-code/sessions/{session}/drift", operationId="admin.course-as-code.sessions.by-session.drift.post", summary="Read admin edits made after the last apply back into a proposed author version", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="dryRun", type="boolean"))),
 *     @OA\Response(response=201, description="proposed version with what was absorbed and what could not be mapped back", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=200, description="nothing to absorb (or dryRun): the same listing, no version", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown session"), @OA\Response(response=409, description="NO_BLUEPRINT or NOT_APPLIED"), @OA\Response(response=422, description="INVALID_BLUEPRINT"))
 * @OA\Get(path="/api/admin/course-as-code/versions/{version}/diff", operationId="admin.course-as-code.versions.by-version.diff.get", summary="Element-aware diff of a version against its parent or ?against=", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="against", in="query", @OA\Schema(type="string")), @OA\Response(response=200, description="changes", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Post(path="/api/admin/course-as-code/versions/{version}/approve", operationId="admin.course-as-code.versions.by-version.approve.post", summary="Approve a proposed import (people and CLI tokens; never agents or MCP clients)", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="approved; apply it next", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
 *     @OA\Response(response=403, description="APPROVAL_REQUIRES_HUMAN"), @OA\Response(response=409, description="not proposed, or STALE_PROPOSAL (the course changed after the import was proposed)"))
 * @OA\Post(path="/api/admin/course-as-code/sessions/{session}/apply", operationId="admin.course-as-code.sessions.by-session.apply.post", summary="Apply the current version to the course (no AI needed)", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=202, description="apply run", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 * @OA\Post(path="/api/admin/course-as-code/sessions/{session}/publish", operationId="admin.course-as-code.sessions.by-session.publish.post", summary="Publish the applied course (no AI needed)", tags={"Admin Course-as-code"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="published", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=409, description="blocking items or unacknowledged warnings"))
 */
class CourseAsCodeController extends Controller
{
    use ResolvesSessions;

    public function __construct(
        private readonly CourseExporter $exporter,
        private readonly CourseImporter $importer,
        private readonly ApprovalGuard $approvals,
        private readonly VersionService $versions,
        private readonly EventLog $events,
        private readonly DriftAbsorber $drift,
        private readonly CourseAdopter $adopter,
    ) {
    }

    public function export(Request $request, string $course): JsonResponse
    {
        $this->assertCanUse($request);
        $id = ctype_digit($course) ? (int) $course : 0;
        if ($id === 0 || Course::query()->whereKey($id)->doesntExist()) {
            throw new NotFoundHttpException('Course not found.');
        }
        $sessions = Session::query()->where('course_id', $id)->latest('updated_at')->get();
        if ($sessions->isEmpty()) {
            return self::error('This course was not made with the builder, so it has no blueprint yet.', 409, 'NOT_ADOPTED');
        }
        $mine = $sessions->first(fn (Session $s) => (int) $s->author_id === (int) $request->user()->getAuthIdentifier());
        $session = $this->sessionFor($request, ($mine ?? $sessions->first())->id);

        return $this->guard(fn () => self::ok($this->exporter->export($session)));
    }

    /**
     * Adopts a course that was made by hand: a first blueprint, an approved version, a builder session and an entity map made from the
     * live course (plan 7.3). Idempotent: an adopted course answers with its session (200). Needs the right to update the course.
     */
    public function adopt(Request $request, string $course): JsonResponse
    {
        $this->assertCanUse($request);
        $model = ctype_digit($course) ? Course::query()->find((int) $course) : null;
        if ($model === null) {
            throw new NotFoundHttpException('Course not found.');
        }
        if (!Gate::forUser($request->user())->allows('update', $model)) {
            throw new AccessDeniedHttpException('You may not change this course.');
        }

        return $this->guard(function () use ($request, $model) {
            $result = $this->adopter->adopt($model, (int) $request->user()->getAuthIdentifier());
            if ($result['created']) {
                $this->events->custom($result['session'], null, 'course_as_code.adopted', array_filter($this->actor($request) + ['versionId' => $result['version']->id, 'skipped' => count($result['skipped'])], fn ($v) => $v !== null));
            }

            return self::ok([
                'created' => $result['created'],
                'courseId' => (int) $model->getKey(),
                'sessionId' => $result['session']->id,
                'versionId' => $result['version']->id,
                'versionNumber' => $result['version']->number,
                'skipped' => $result['skipped'],
            ], $result['created'] ? 201 : 200);
        });
    }

    /** A new, empty course for a course folder that has no course on the server yet (a preview academy): an approved empty blueprint the first import builds on. */
    public function create(Request $request): JsonResponse
    {
        $this->assertCanUse($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200', 'regex:/^[^<>]*$/'],
            'language' => ['nullable', 'string', 'regex:/^[a-z]{2}$/'],
            'key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_.:#\/-]{1,80}$/'],
        ]);
        $userId = (int) $request->user()->getAuthIdentifier();
        $key = $data['key'] ?? null;
        if ($key !== null) {
            // the same key asks for the same course again (a pull request pushed twice): its session comes back, nothing is created
            $existing = Session::query()->where('author_id', $userId)->where('state->course_as_code_key', $key)->latest('created_at')->first();
            if ($existing?->currentVersion !== null) {
                return self::ok(['sessionId' => $existing->id, 'versionId' => $existing->current_version_id, 'versionNumber' => $existing->currentVersion->number, 'courseId' => $existing->course_id, 'existing' => true]);
            }
        }
        $session = Session::query()->create(['author_id' => $userId] + ($key !== null ? ['state' => ['course_as_code_key' => $key]] : []));
        $version = $this->versions->create($session, Blueprint::empty(trim($data['title']), $data['language'] ?? 'en'), 'content', 'author', Version::APPROVED, null, 'Created from a course repository', null, [], $userId);
        $this->versions->setCurrent($session, $version);
        $this->events->custom($session, null, 'course_as_code.created', array_filter($this->actor($request) + ['versionId' => $version->id], fn ($v) => $v !== null));

        return self::ok(['sessionId' => $session->id, 'versionId' => $version->id, 'versionNumber' => $version->number, 'courseId' => null, 'existing' => false], 201);
    }

    /** Where a session stands: its course (made by the first apply), the current and the applied version. What a client polls while an apply runs. */
    public function session(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);

        return self::ok([
            'sessionId' => $s->id,
            'courseId' => $s->course_id,
            'status' => $s->status,
            'currentVersionId' => $s->current_version_id,
            'currentVersionNumber' => $s->currentVersion?->number,
            'appliedVersionId' => $s->applied_version_id,
        ]);
    }

    public function import(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        if (strlen($request->getContent()) > (int) config('course_as_code.max_document_bytes', 8 * 1024 * 1024)) {
            return self::error('The document is larger than this installation accepts.', 413, 'DOCUMENT_TOO_LARGE');
        }
        $data = $request->validate([
            'document' => ['required', 'array'],
            'baseVersionId' => ['required', 'string', 'regex:/^[0-9a-z]{26}$/i'],
            'message' => ['nullable', 'string', 'max:500'],
            'allowPending' => ['nullable', 'boolean'],
        ]);

        return $this->guard(function () use ($request, $s, $data) {
            $result = $this->importer->import($s, $data['document'], strtolower($data['baseVersionId']), $data['message'] ?? null, $this->actor($request), (bool) ($data['allowPending'] ?? false));
            $version = $result['version'];

            return self::ok([
                'unchanged' => $result['unchanged'],
                'version' => $version === null ? null : ['id' => $version->id, 'number' => $version->number, 'kind' => $version->kind, 'status' => $version->status, 'parentId' => $version->parent_id],
                'counts' => $result['counts'],
                'changes' => $result['changes'],
                'warnings' => $result['warnings'],
                'handEdited' => $result['handEdited'],
                'merged' => $result['merged'],
            ], $version === null ? 200 : 201);
        });
    }

    public function approve(Request $request, string $version): JsonResponse
    {
        $v = preg_match('/^[0-9a-z]{26}$/i', $version) ? Version::query()->find(strtolower($version)) : null;
        if ($v === null) {
            throw new NotFoundHttpException('Version not found.');
        }
        $s = $this->sessionFor($request, $v->session_id, 'update');
        if (!in_array($v->kind, ['import', 'author'], true)) {
            // outline, patch and content proposals keep their own flow (and its gates)
            return app(CourseBuilderController::class)->approve($request, $version);
        }

        return $this->guard(function () use ($request, $s, $v) {
            $this->approvals->assertMayApprove($request);
            if ($v->status !== Version::PROPOSED) {
                return self::error('This version is not waiting for a decision.', 409, 'NOT_PROPOSED');
            }
            if ($v->parent_id !== $s->current_version_id) {
                return self::error('The course changed after this import was proposed. Import again from the current version.', 409, 'STALE_PROPOSAL', ['currentVersionId' => $s->current_version_id]);
            }
            $this->versions->approve($v, (int) $request->user()->getAuthIdentifier());
            $this->versions->setCurrent($s, $v);
            $this->events->custom($s, null, 'course_as_code.approved', array_filter($this->actor($request) + ['versionId' => $v->id, 'number' => $v->number], fn ($x) => $x !== null));

            return self::ok(['versionId' => $v->id, 'number' => $v->number, 'status' => Version::APPROVED, 'currentVersionId' => $v->id, 'applied' => false]);
        });
    }

    public function absorbDrift(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session, 'update');
        $dryRun = $request->boolean('dryRun');

        return $this->guard(function () use ($request, $s, $dryRun) {
            $result = $this->drift->absorb($s, $this->actor($request), $dryRun);

            return self::ok($result, ($result['version'] ?? null) === null ? 200 : 201);
        });
    }

    /** @return array{userId:int,tokenId:?string,tokenKind:?string,client:?string,commit:?string,changeUrl:?string} */
    private function actor(Request $request): array
    {
        $header = fn (string $name) => ($value = trim((string) $request->header($name))) !== '' ? mb_substr($value, 0, 500) : null;

        return [
            'userId' => (int) $request->user()->getAuthIdentifier(),
            'tokenId' => ApprovalGuard::tokenId($request),
            'tokenKind' => ApprovalGuard::tokenKind($request),
            'client' => $header('X-Ulams-Client'),
            'commit' => $header('X-Ulams-Commit'),
            'changeUrl' => $header('X-Ulams-Change-Url'),
        ];
    }

    private function guard(\Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (CourseAsCodeException $e) {
            return self::error($e->getMessage(), $e->status, $e->errorCode, $e->data);
        }
    }

    private static function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }

    /** @param array<string,mixed> $data */
    private static function error(string $message, int $status, string $code, array $data = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'code' => $code] + ($data === [] ? [] : ['data' => $data]), $status);
    }
}
