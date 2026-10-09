<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Support\Presenter;

/**
 * Sources of a builder session with their sync state, and their revisions.
 *
 * @OA\Get(path="/api/admin/living-course/sessions/{session}/sources", summary="Sources of a session with connection, sync state and revisions", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="sources"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown session"))
 * @OA\Get(path="/api/admin/living-course/sources/{source}/revisions", summary="Revisions of a source, newest first", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="source", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="revisions"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown source"))
 * @OA\Get(path="/api/admin/living-course/revisions/{revision}", summary="One revision", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="revision", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="revision"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown revision"))
 */
class SourcesController extends Controller
{
    use ResolvesLivingCourse;

    public function index(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);
        $connections = Connection::query()->where('session_id', $s->id)->get()->keyBy('source_id');

        return self::ok($s->sources()->get()->map(fn ($source) => Presenter::source($source, $connections->get($source->id)))->all());
    }

    public function revisions(Request $request, string $source): JsonResponse
    {
        [, $src] = $this->sourceFor($request, $source);
        $connection = Connection::query()->where('source_id', $src->id)->first();
        $revisions = $connection?->revisions()->reorder('number', 'desc')->get() ?? collect();

        return self::ok($revisions->map(fn ($r) => Presenter::revision($r, $connection))->all());
    }

    public function revision(Request $request, string $revision): JsonResponse
    {
        [, $r] = $this->revisionFor($request, $revision);

        return self::ok(Presenter::revision($r, $r->connection));
    }
}
