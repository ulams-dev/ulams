<?php

namespace Ulams\Adapt\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Ulams\Adapt\Jobs\BuildAdaptSource;
use Ulams\Adapt\Models\AdaptSource;
use Ulams\Adapt\Models\AdaptSourceVersion;
use Ulams\Adapt\Services\AdaptSourceValidator;

/**
 * Adapt Path B (behind ADAPT_SOURCE_ENABLED; 404 when off). Permission `adapt_manage`.
 *
 * @OA\Get(path="/api/admin/adapt", summary="List Adapt JSON sources", tags={"Admin Adapt"}, security={{"passport": {}}}, @OA\Response(response=200, description="sources"))
 * @OA\Post(path="/api/admin/adapt", summary="Create from Adapt JSON (course, config, contentObjects, articles, blocks, components)", tags={"Admin Adapt"}, security={{"passport": {}}},
 *     @OA\Response(response=201, description="source, version 1"), @OA\Response(response=422, description="structural errors with paths"))
 * @OA\Get(path="/api/admin/adapt/{id}", summary="Show a source and its build status", tags={"Admin Adapt"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="source"))
 * @OA\Get(path="/api/admin/adapt/{id}/source", summary="JSON of the current or a given version", tags={"Admin Adapt"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Adapt JSON"))
 * @OA\Get(path="/api/admin/adapt/{id}/versions", summary="Version history (version, change note, author, date; no source)", tags={"Admin Adapt"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="versions, newest first"))
 * @OA\Post(path="/api/admin/adapt/{id}/versions", summary="Add a version", tags={"Admin Adapt"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=201, description="source"))
 * @OA\Post(path="/api/admin/adapt/{id}/build", summary="Build the current version into a SCORM package (queued)", tags={"Admin Adapt"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=202, description="queued"))
 * @OA\Delete(path="/api/admin/adapt/{id}", summary="Delete a source (the built SCORM package stays)", tags={"Admin Adapt"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="deleted"))
 */
class AdaptSourceController extends Controller
{
    public function __construct(private readonly AdaptSourceValidator $validator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request);

        return $this->ok(AdaptSource::query()->orderByDesc('updated_at')->limit(500)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request);
        $source = $this->validSource($request);

        $model = DB::transaction(function () use ($request, $source) {
            $model = AdaptSource::query()->create([
                'title' => mb_substr((string) ($request->input('title') ?: $source['course']['title']), 0, 255),
                'current_version' => 1,
                'author_id' => $request->user()?->getKey(),
            ]);
            AdaptSourceVersion::query()->create([
                'adapt_source_id' => $model->getKey(),
                'version' => 1,
                'source' => $source,
                'author_id' => $request->user()?->getKey(),
            ]);

            return $model;
        });

        return $this->ok($model->refresh(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->guard($request);

        return $this->ok(AdaptSource::query()->findOrFail($id));
    }

    public function source(Request $request, int $id): JsonResponse
    {
        $this->guard($request);
        $model = AdaptSource::query()->findOrFail($id);
        $version = $model->version((int) ($request->query('version') ?: $model->current_version));
        abort_if($version === null, 404);

        return response()->json($version->source)->header('X-Adapt-Version', (string) $version->version);
    }

    public function versions(Request $request, int $id): JsonResponse
    {
        $this->guard($request);
        $model = AdaptSource::query()->findOrFail($id);
        $versions = $model->versions()->reorder('version', 'desc')->get(['version', 'change_note', 'author_id', 'created_at']);

        return $this->ok($versions);
    }

    public function addVersion(Request $request, int $id): JsonResponse
    {
        $this->guard($request);
        $source = $this->validSource($request);

        $model = DB::transaction(function () use ($request, $id, $source) {
            /** @var AdaptSource $model */
            $model = AdaptSource::query()->lockForUpdate()->findOrFail($id);
            $next = $model->current_version + 1;
            AdaptSourceVersion::query()->create([
                'adapt_source_id' => $model->getKey(),
                'version' => $next,
                'source' => $source,
                'change_note' => $request->input('change_note') ? mb_substr((string) $request->input('change_note'), 0, 500) : null,
                'author_id' => $request->user()?->getKey(),
            ]);
            $model->update(['current_version' => $next, 'status' => $model->status === AdaptSource::BUILDING ? AdaptSource::BUILDING : AdaptSource::DRAFT]);

            return $model;
        });

        return $this->ok($model->refresh(), 201);
    }

    public function build(Request $request, int $id): JsonResponse
    {
        $this->guard($request);
        $model = AdaptSource::query()->findOrFail($id);
        if ($model->status === AdaptSource::BUILDING) {
            return response()->json(['success' => false, 'message' => 'A build is already running.'], 409);
        }
        $model->update(['status' => AdaptSource::BUILDING, 'last_error' => null]);
        BuildAdaptSource::dispatch($model->getKey(), $model->current_version);

        return $this->ok($model->refresh(), 202);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->guard($request);
        AdaptSource::query()->findOrFail($id)->delete();

        return $this->ok(null);
    }

    private function guard(Request $request): void
    {
        abort_unless((bool) config('ulams_adapt.enabled'), 404);
        abort_unless((bool) $request->user()?->can('adapt_manage', 'api'), 403);
    }

    private function validSource(Request $request): array
    {
        $raw = $request->getContent();
        abort_if(strlen($raw) > (int) config('ulams_adapt.max_source_bytes', 4 * 1024 * 1024), 413, 'The Adapt source is too large.');
        $source = $request->input('source');
        $errors = $this->validator->validate($source);
        if ($errors !== []) {
            abort(response()->json(['success' => false, 'message' => 'The Adapt source is not valid.', 'errors' => ['source' => $errors]], 422));
        }

        return $source;
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }
}
