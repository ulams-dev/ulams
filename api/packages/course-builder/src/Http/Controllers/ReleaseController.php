<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesSessions;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Ui\Surfaces;

/**
 * Course releases (ADR 0192): the versions of a course that learners actually got, newest first. Keyed by the LMS
 * course id, like the Insights page. No AI gate: it works with AI switched off. Visible to the session author and to
 * tenant admins (the session policy); read-only, a release is written by the apply step only.
 *
 * @OA\Get(path="/api/admin/course-versions/courses/{course}/releases", operationId="admin.course-versions.courses.by-course.releases.get", summary="Releases of a course (what learners got), newest first, with a summary of what changed", tags={"Admin Course Versions"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")), @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")), @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", maximum=100)),
 *     @OA\Response(response=200, description="releases, total, page, perPage, sessionId", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="another author's course"), @OA\Response(response=404, description="the course was not made with the Course Builder"))
 * @OA\Get(path="/api/admin/course-versions/releases/{release}", operationId="admin.course-versions.releases.by-release.get", summary="One release with the blueprint document of its version", tags={"Admin Course Versions"}, security={{"passport": {}}},
 *     @OA\Parameter(name="release", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="release and document", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="another author's course"), @OA\Response(response=404, description="unknown release"))
 */
class ReleaseController extends Controller
{
    use ResolvesSessions;

    private static function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK']);
    }

    public function index(Request $request, string $course): JsonResponse
    {
        $this->assertCanUse($request);
        $session = Session::query()->where('course_id', (int) $course)->orderByDesc('updated_at')->first();
        if ($session === null) {
            throw new NotFoundHttpException('This course was not made with the Course Builder, so it has no releases. Adopt the course to track its versions.');
        }
        $this->sessionFor($request, $session->id);

        $perPage = min(100, max(1, (int) $request->query('perPage', 25)));
        $page = Release::query()->where('session_id', $session->id)->whereNull('variant_id')->orderByDesc('number')->paginate($perPage);
        $versions = Version::query()->whereIn('id', $page->getCollection()->pluck('version_id'))->get(['id', 'number', 'reason'])->keyBy('id');
        $names = $this->names($page->getCollection()->pluck('approved_by')->filter()->unique()->values()->all());

        return self::ok([
            'sessionId' => $session->id,
            'courseId' => (int) $course,
            'releases' => $page->getCollection()->map(fn (Release $r) => self::present($r, $versions[$r->version_id] ?? null, $names))->all(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
        ]);
    }

    public function show(Request $request, string $release): JsonResponse
    {
        $this->assertCanUse($request);
        $r = preg_match('/^[0-9a-z]{26}$/i', $release) ? Release::query()->find(strtolower($release)) : null;
        if ($r === null) {
            throw new NotFoundHttpException('Release not found.');
        }
        $this->sessionFor($request, $r->session_id);
        $version = Version::query()->where('session_id', $r->session_id)->find($r->version_id);

        return self::ok(self::present($r, $version, $this->names(array_filter([$r->approved_by]))) + [
            'document' => $version?->document,
            'fragments' => $version !== null ? Surfaces::labels($version->document) : [],
        ]);
    }

    /** @param array<int,string> $names */
    private static function present(Release $r, ?Version $version, array $names): array
    {
        return [
            'id' => $r->id,
            'number' => $r->number,
            'trigger' => $r->trigger,
            'versionId' => $r->version_id,
            'versionNumber' => $version?->number,
            'reason' => $version?->reason ? mb_substr($version->reason, 0, 1000) : null,
            'previousReleaseId' => $r->previous_release_id,
            'courseId' => $r->course_id,
            'approvedBy' => $r->approved_by !== null ? ['id' => $r->approved_by, 'name' => $names[$r->approved_by] ?? '#' . $r->approved_by] : null,
            'sourceRevisions' => $r->source_revisions,
            'summary' => $r->summary,
            'releasedAt' => $r->released_at->toIso8601String(),
        ];
    }

    /** @param int[] $ids @return array<int,string> */
    private function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $model = config('auth.providers.users.model');

        return $model::query()->whereIn('id', $ids)->get()->mapWithKeys(fn ($u) => [$u->getKey() => trim((string) ($u->name ?? $u->email ?? '')) ?: ('#' . $u->getKey())])->all();
    }
}
