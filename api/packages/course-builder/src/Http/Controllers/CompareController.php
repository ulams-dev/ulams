<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Blueprint\SemanticDiff;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesCourseBranch;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesSessions;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionComparer;

/**
 * Compare any two versions or releases of a course (ADR 0192, CV-2). Keyed by the LMS course id like the release list;
 * `from` and `to` are version ids or release ids of the same builder session (a variant course can be compared with its
 * base this way). No AI gate: it works with AI switched off, and it makes no model call. Read-only; visible to the
 * session author and to tenant admins (the session policy). A pair that names something of another session is a 404.
 *
 * @OA\Get(path="/api/admin/course-versions/courses/{course}/compare", operationId="admin.course-versions.courses.by-course.compare.get", summary="Semantic diff of two versions or releases of a course: moved elements, citations, quiz answer keys and a summary", tags={"Admin Course Versions"}, security={{"passport": {}}},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="from", in="query", required=true, description="Version id or release id (the older side)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="to", in="query", required=true, description="Version id or release id (the newer side)", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="diffVersion, summary, changes, quiz, fragments, from, to, courseId, sessionId", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="another author's course"), @OA\Response(response=404, description="the course was not made with the Course Builder, or a version or release is not part of it"), @OA\Response(response=422, description="from or to is missing"))
 */
class CompareController extends Controller
{
    use ResolvesCourseBranch;
    use ResolvesSessions;

    public function __construct(private readonly VersionComparer $comparer)
    {
    }

    public function show(Request $request, string $course): JsonResponse
    {
        $this->assertCanUse($request);
        [$session] = $this->branchOf((int) $course, 'versions');
        $this->sessionFor($request, $session->id);
        $request->validate(['from' => ['required', 'string', 'max:64'], 'to' => ['required', 'string', 'max:64']]);

        [$from, $fromRelease] = $this->resolve($session, (string) $request->query('from'));
        [$to, $toRelease] = $this->resolve($session, (string) $request->query('to'));
        $payload = $this->comparer->compare($from, $to);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => [
            'diffVersion' => SemanticDiff::VERSION,
            'courseId' => (int) $course,
            'sessionId' => $session->id,
            'from' => self::side($from, $fromRelease),
            'to' => self::side($to, $toRelease),
        ] + $payload]);
    }

    /** @return array{0:Version,1:?Release} a version id, or a release id that stands for its version */
    private function resolve(Session $session, string $id): array
    {
        $id = strtolower($id);
        if (preg_match('/^[0-9a-z]{26}$/', $id)) {
            $version = Version::query()->where('session_id', $session->id)->find($id);
            if ($version !== null) {
                return [$version, Release::query()->where('session_id', $session->id)->where('version_id', $version->id)->orderByDesc('number')->first()];
            }
            $release = Release::query()->where('session_id', $session->id)->find($id);
            $version = $release !== null ? Version::query()->where('session_id', $session->id)->find($release->version_id) : null;
            if ($version !== null) {
                return [$version, $release];
            }
        }
        throw new NotFoundHttpException('That version or release is not part of this course.');
    }

    private static function side(Version $version, ?Release $release): array
    {
        return [
            'versionId' => $version->id,
            'versionNumber' => $version->number,
            'kind' => $version->kind,
            'status' => $version->status,
            'variantId' => $version->variant_id,
            'releaseId' => $release?->id,
            'releaseNumber' => $release?->number,
            'releasedAt' => $release?->released_at->toIso8601String(),
            'createdAt' => $version->created_at?->toIso8601String(),
        ];
    }
}
