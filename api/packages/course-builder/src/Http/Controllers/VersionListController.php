<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesCourseBranch;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesSessions;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;

/**
 * Every version of a course's branch, not only the ones learners got (ADR 0192, CV-3): proposals, rejected and undone
 * versions as well, newest first, for the "show all versions" toggle and the pickers of the compare screen. Keyed by the
 * LMS course id like the release list. `branch=all` lists the versions of every branch of the session (the base and its
 * variants), so a variant can be picked against the base. The variants of the session come with it. No AI gate and no
 * model call; read-only; visible to the session author and to tenant admins (the session policy). The documents are not
 * part of the list: the compare endpoint reads them.
 *
 * @OA\Get(path="/api/admin/course-versions/courses/{course}/versions", operationId="admin.course-versions.courses.by-course.versions.get", summary="All versions of a course's branch (proposals, rejected and undone ones too), newest first, with the session's variants", tags={"Admin Course Versions"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")), @OA\Parameter(name="branch", in="query", description="`all` lists the versions of every branch of the session", @OA\Schema(type="string", enum={"own","all"})), @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")), @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", maximum=200)),
 *     @OA\Response(response=200, description="versions, variants, total, page, perPage, sessionId, variantId, courseId, baseCourseId", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="another author's course"), @OA\Response(response=404, description="the course was not made with the Course Builder"))
 */
class VersionListController extends Controller
{
    use ResolvesCourseBranch;
    use ResolvesSessions;

    public function index(Request $request, string $course): JsonResponse
    {
        $this->assertCanUse($request);
        [$session, $variant] = $this->branchOf((int) $course, 'versions');
        $this->sessionFor($request, $session->id);

        $all = $request->query('branch') === 'all';
        $perPage = min(200, max(1, (int) $request->query('perPage', 100)));
        $page = Version::query()
            ->where('session_id', $session->id)
            ->when(!$all, fn ($q) => $variant === null ? $q->whereNull('variant_id') : $q->where('variant_id', $variant->id))
            ->orderByDesc('created_at')->orderByDesc('number')
            ->paginate($perPage, ['id', 'session_id', 'number', 'kind', 'origin', 'status', 'reason', 'variant_id', 'element_id', 'created_at', 'decided_at']);

        $ids = $page->getCollection()->pluck('id')->all();
        $latest = [];
        foreach (Release::query()->where('session_id', $session->id)->whereIn('version_id', $ids)->orderBy('number')->get(['id', 'number', 'version_id', 'released_at']) as $release) {
            $latest[$release->version_id] = $release;
        }

        return response()->json(['success' => true, 'message' => 'OK', 'data' => [
            'sessionId' => $session->id,
            'variantId' => $variant?->id,
            'courseId' => (int) $course,
            'baseCourseId' => $session->course_id !== null ? (int) $session->course_id : null,
            'versions' => $page->getCollection()->map(fn (Version $v) => [
                'id' => $v->id,
                'number' => $v->number,
                'kind' => $v->kind,
                'origin' => $v->origin,
                'status' => $v->status,
                'reason' => $v->reason ? mb_substr($v->reason, 0, 1000) : null,
                'variantId' => $v->variant_id,
                'releaseId' => ($latest[$v->id] ?? null)?->id,
                'releaseNumber' => ($latest[$v->id] ?? null)?->number,
                'createdAt' => $v->created_at?->toIso8601String(),
                'decidedAt' => $v->decided_at?->toIso8601String(),
            ])->all(),
            'variants' => Variant::query()->where('session_id', $session->id)->orderBy('position')->orderBy('created_at')->get()->map(fn (Variant $x) => [
                'id' => $x->id,
                'key' => $x->key,
                'label' => $x->label,
                'level' => $x->level,
                'role' => $x->role,
                'status' => $x->status,
                'courseId' => $x->course_id,
            ])->all(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
        ]]);
    }
}
