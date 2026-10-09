<?php

namespace Ulams\LiaScript\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Ulams\LiaScript\Http\Requests\LiaScriptRequest;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\LiaScript\Models\LiaScriptVersion;
use Ulams\LiaScript\Services\LiaScriptPlayer;
use Ulams\LiaScript\Services\LiaScriptService;

/**
 * LiaScript sources (spec 1.1): versioned Markdown plus assets.
 *
 * @OA\Get(path="/api/admin/liascript", summary="List LiaScript sources", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="documents"), @OA\Response(response=403, description="liascript_manage required"))
 * @OA\Post(path="/api/admin/liascript", summary="Create from Markdown, an .md file or a .zip (Markdown + assets)", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
 *         @OA\Property(property="title", type="string"), @OA\Property(property="markdown", type="string"), @OA\Property(property="file", type="string", format="binary")))),
 *     @OA\Response(response=201, description="document with version 1 and warnings"), @OA\Response(response=422, description="invalid Markdown or upload"))
 * @OA\Get(path="/api/admin/liascript/{id}", summary="Show a LiaScript source", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="document"))
 * @OA\Put(path="/api/admin/liascript/{id}", summary="Rename a LiaScript source", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="document"))
 * @OA\Delete(path="/api/admin/liascript/{id}", summary="Delete a LiaScript source with all versions and assets", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="deleted"))
 * @OA\Get(path="/api/admin/liascript/{id}/source", summary="Markdown of the current or a given version", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="version", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="text/markdown", @OA\MediaType(mediaType="text/markdown")))
 * @OA\Get(path="/api/admin/liascript/{id}/versions", summary="List versions", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="versions"))
 * @OA\Post(path="/api/admin/liascript/{id}/versions", summary="Add a version from Markdown or an upload", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=201, description="document"))
 * @OA\Post(path="/api/admin/liascript/{id}/versions/{version}/restore", summary="Restore a version (adds a new version with its content)", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="version", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=201, description="document"))
 * @OA\Post(path="/api/admin/liascript/{id}/preview", summary="Live preview of unsaved Markdown on the tenant content origin", tags={"Admin LiaScript"}, security={{"passport": {}}},
 *     description="Publishes the text as a short-lived draft next to the current version (its assets resolve) and returns a player URL without progress tracking. Nothing is saved as a version.",
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\JsonContent(required={"markdown"}, @OA\Property(property="markdown", type="string"))),
 *     @OA\Response(response=200, description="{url, sections, warnings}"), @OA\Response(response=422, description="invalid Markdown"),
 *     @OA\Response(response=503, description="no content origin or player not installed"))
 */
class LiaScriptController extends Controller
{
    public function __construct(
        private readonly LiaScriptService $service,
        private readonly LiaScriptPlayer $player,
    ) {
    }

    public function index(LiaScriptRequest $request): JsonResponse
    {
        $documents = LiaScriptDocument::query()->withCount('versions')->orderByDesc('updated_at')->paginate((int) $request->query('per_page', 25));

        return response()->json(['success' => true, 'data' => $documents->items(), 'meta' => [
            'current_page' => $documents->currentPage(), 'last_page' => $documents->lastPage(), 'total' => $documents->total(),
        ], 'message' => 'OK']);
    }

    public function store(LiaScriptRequest $request): JsonResponse
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'markdown' => ['required_without:file', 'nullable', 'string'],
            'file' => ['required_without:markdown', 'nullable', 'file'],
        ]);
        $document = $this->service->create(
            $request->input('title'),
            $request->input('markdown'),
            $request->file('file'),
            $request->user()?->getKey()
        );

        return $this->document($document, 201);
    }

    public function show(LiaScriptRequest $request, int $id): JsonResponse
    {
        return $this->document(LiaScriptDocument::query()->findOrFail($id));
    }

    public function update(LiaScriptRequest $request, int $id): JsonResponse
    {
        $request->validate(['title' => ['required', 'string', 'max:255']]);
        $document = $this->service->update(LiaScriptDocument::query()->findOrFail($id), $request->input('title'), null, null, null, $request->user()?->getKey());

        return $this->document($document);
    }

    public function addVersion(LiaScriptRequest $request, int $id): JsonResponse
    {
        $request->validate([
            'markdown' => ['required_without:file', 'nullable', 'string'],
            'file' => ['required_without:markdown', 'nullable', 'file'],
            'change_note' => ['nullable', 'string', 'max:500'],
        ]);
        $document = $this->service->update(
            LiaScriptDocument::query()->findOrFail($id),
            null,
            $request->input('markdown'),
            $request->file('file'),
            $request->input('change_note'),
            $request->user()?->getKey()
        );

        return $this->document($document, 201);
    }

    public function destroy(LiaScriptRequest $request, int $id): JsonResponse
    {
        if (LiaScriptTopic::query()->where('value', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Topics use this course; remove them first.'], 409);
        }
        $this->service->delete(LiaScriptDocument::query()->findOrFail($id));

        return response()->json(['success' => true, 'data' => null, 'message' => 'Deleted']);
    }

    public function source(LiaScriptRequest $request, int $id): Response
    {
        $request->validate(['version' => ['nullable', 'integer', 'min:1']]);
        $version = $this->service->source(LiaScriptDocument::query()->findOrFail($id), $request->integer('version') ?: null);

        return response($version->markdown, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-LiaScript-Version' => (string) $version->version,
        ]);
    }

    public function versions(LiaScriptRequest $request, int $id): JsonResponse
    {
        $document = LiaScriptDocument::query()->findOrFail($id);

        return response()->json(['success' => true, 'data' => $document->versions->map(fn (LiaScriptVersion $v) => $v->summary())->all(), 'message' => 'OK']);
    }

    public function restore(LiaScriptRequest $request, int $id, int $version): JsonResponse
    {
        $document = $this->service->restore(LiaScriptDocument::query()->findOrFail($id), $version, $request->user()?->getKey());

        return $this->document($document, 201);
    }

    public function preview(LiaScriptRequest $request, int $id): JsonResponse
    {
        $request->validate(['markdown' => ['required', 'string']]);
        $document = LiaScriptDocument::query()->findOrFail($id);
        $markdown = $this->service->previewMarkdown((string) $request->input('markdown'));
        $origin = rtrim(trim((string) (config('ulams_uploads.content_origin') ?: config('scorm.content_origin'))), '/');
        if ($origin === '' || !$this->player->installed()) {
            return response()->json(['success' => false, 'message' => 'The preview needs the content origin and the LiaScript player.'], 503);
        }
        $current = $this->service->source($document);

        $this->player->publishPlayer();
        $path = $this->player->publishPreview($document, $current, $markdown);
        $sections = LiaScriptPlayer::sections($markdown);
        $fragment = http_build_query(['preview' => 1, 'course' => $path, 'sections' => $sections], '', '&', PHP_QUERY_RFC3986);

        return response()->json(['success' => true, 'data' => [
            'url' => $origin . '/' . LiaScriptPlayer::PLAYER_DIR . '/index.html#' . $fragment,
            'sections' => $sections,
            'warnings' => $this->service->warnings($markdown),
        ], 'message' => 'OK']);
    }

    private function document(LiaScriptDocument $document, int $status = 200): JsonResponse
    {
        $current = $document->version($document->current_version);

        return response()->json(['success' => true, 'data' => [
            'id' => $document->getKey(),
            'title' => $document->title,
            'current_version' => $document->current_version,
            'versions_count' => $document->versions()->count(),
            'assets' => array_keys($current?->assets ?? []),
            'warnings' => $current ? $this->service->warnings($current->markdown) : [],
            'author_id' => $document->author_id,
            'updated_at' => $document->updated_at?->toIso8601String(),
        ], 'message' => 'OK'], $status);
    }
}
