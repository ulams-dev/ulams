<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\LivingCourse\Diff\FragmentDiff;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Services\SourceSync;
use Ulams\LivingCourse\Support\Presenter;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Sources of a builder session with their sync state, and their revisions.
 *
 * @OA\Get(path="/api/admin/living-course/sessions/{session}/sources", summary="Sources of a session with connection, sync state and revisions", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="sources"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown session"))
 * @OA\Get(path="/api/admin/living-course/sources/{source}/revisions", summary="Revisions of a source, newest first", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="source", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="revisions"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown source"))
 * @OA\Post(path="/api/admin/living-course/sources/{source}/revisions", summary="Upload a new version of a source (MD, PDF or DOCX): creates a revision and its fragment diff", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="source", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
 *     @OA\Response(response=201, description="the new revision"), @OA\Response(response=200, description="the file is the latest revision already (unchanged: true)"), @OA\Response(response=422, description="rejected upload"))
 * @OA\Get(path="/api/admin/living-course/revisions/{revision}/changes", summary="Fragment changes of a revision against the synced revision it was compared with (or ?against=<revision id>)", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="revision", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="against", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="counts and changes with old and new text and word diffs"), @OA\Response(response=404, description="unknown revision"))
 * @OA\Get(path="/api/admin/living-course/revisions/{revision}", summary="One revision", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="revision", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="revision"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown revision"))
 */
class SourcesController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(private readonly SourceSync $sync)
    {
    }

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

    public function upload(Request $request, string $source): JsonResponse
    {
        [, $src] = $this->sourceFor($request, $source, 'act');
        $request->validate(['file' => ['required', 'file']]);
        if ($src->status !== 'ready') {
            return self::fail('This source is not ready yet. Wait for its first import to finish.', 409);
        }
        try {
            $result = $this->sync->upload($src, $request->file('file'), (int) $request->user()->getKey());
        } catch (UploadRejected $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]], 'reason' => $e->reason], 422);
        } catch (RuntimeException $e) {
            return self::fail($e->getMessage(), 422);
        }
        $connection = $result['revision']->connection;

        return self::ok(['unchanged' => $result['unchanged'], 'revision' => Presenter::revision($result['revision'], $connection)], $result['created'] ? 201 : 200);
    }

    public function changes(Request $request, string $revision): JsonResponse
    {
        [, $to] = $this->revisionFor($request, $revision);
        $against = $request->query('against');
        if (is_string($against) && $against !== '') {
            $from = Revision::query()->where('source_id', $to->source_id)->find(strtolower($against));
            if ($from === null) {
                throw new NotFoundHttpException('Revision not found.');
            }
            $set = (new FragmentDiff())->compareRevisions($from, $to);
            $rows = $set->changes;
            $counts = $set->counts();
        } else {
            $stored = FragmentChange::query()->where('to_revision_id', $to->id)->orderBy('id')->get();
            $fromId = $stored->first()?->from_revision_id
                ?? (isset($to->metadata['against']) ? Revision::query()->where('source_id', $to->source_id)->where('number', (int) $to->metadata['against'])->value('id') : null);
            $from = $fromId !== null ? Revision::query()->find($fromId) : null;
            $rows = $stored->map(fn (FragmentChange $c) => ['id' => $c->id, 'kind' => $c->kind, 'old' => $c->old_fragment_id, 'new' => $c->new_fragment_id, 'magnitude' => $c->magnitude, 'similarity' => $c->similarity, 'signals' => $c->signals ?? [], 'word_diff' => $c->word_diff])->all();
            $counts = $to->metadata['counts'] ?? null;
        }
        $connection = $to->connection;

        return self::ok([
            'from' => $from ? Presenter::revision($from, $connection) : null,
            'to' => Presenter::revision($to, $connection),
            'counts' => $counts,
            'changes' => $from ? Presenter::changes($from, $to, $rows) : [],
        ]);
    }

    public function revision(Request $request, string $revision): JsonResponse
    {
        [, $r] = $this->revisionFor($request, $revision);

        return self::ok(Presenter::revision($r, $r->connection));
    }
}
