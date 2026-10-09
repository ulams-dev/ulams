<?php

namespace Ulams\Interactive\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Ulams\Interactive\Http\Requests\InteractivePackageCreateRequest;
use Ulams\Interactive\Http\Requests\InteractivePackageUpdateRequest;
use Ulams\Interactive\Http\Requests\InteractiveRequest;
use Ulams\Interactive\Http\Requests\InteractiveVersionCreateRequest;
use Ulams\Interactive\Http\Resources\InteractivePackageResource;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractivePackageVersion;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;

/**
 * The interactive package library (ADR 0086): uploaded zip packages, versioned and immutable.
 *
 * @OA\Get(path="/api/admin/interactive", summary="List interactive packages", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="packages"), @OA\Response(response=403, description="interactive_manage required"))
 * @OA\Post(path="/api/admin/interactive", summary="Upload a package (.zip with ulams-interactive.json)", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(required={"file"},
 *         @OA\Property(property="file", type="string", format="binary"), @OA\Property(property="title", type="string"), @OA\Property(property="change_note", type="string")))),
 *     @OA\Response(response=201, description="package with version 1"), @OA\Response(response=422, description="rejected upload or invalid manifest"))
 * @OA\Get(path="/api/admin/interactive/{id}", summary="Show a package with its current manifest", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="package"), @OA\Response(response=404, description="not found"))
 * @OA\Put(path="/api/admin/interactive/{id}", summary="Rename a package", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="package"))
 * @OA\Delete(path="/api/admin/interactive/{id}", summary="Delete a package with all versions and files", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="deleted"), @OA\Response(response=409, description="topics use this package"))
 * @OA\Get(path="/api/admin/interactive/{id}/versions", summary="List versions", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="versions"))
 * @OA\Post(path="/api/admin/interactive/{id}/versions", summary="Upload a new version (the manifest id must match)", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(required={"file"},
 *         @OA\Property(property="file", type="string", format="binary"), @OA\Property(property="change_note", type="string")))),
 *     @OA\Response(response=201, description="package with the new current version"), @OA\Response(response=422, description="rejected upload or invalid manifest"))
 * @OA\Get(path="/api/admin/interactive/{id}/preview", summary="Admin preview of a version on the content origin (nothing is tracked)", tags={"Admin Interactive"}, security={{"passport": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="version", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="{url, nonce, version, manifest}"), @OA\Response(response=503, description="no content origin"))
 */
class InteractivePackageController extends Controller
{
    public function __construct(private readonly InteractivePackageServiceContract $service)
    {
    }

    public function index(InteractiveRequest $request): JsonResponse
    {
        $query = InteractivePackage::query()->withCount(['versions', 'topics'])->orderByDesc('updated_at');
        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where('title', 'ilike', '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%');
        }
        $packages = $query->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        return response()->json(['success' => true, 'data' => InteractivePackageResource::collection($packages->items())->resolve(), 'meta' => [
            'current_page' => $packages->currentPage(), 'last_page' => $packages->lastPage(), 'total' => $packages->total(),
        ], 'message' => 'OK']);
    }

    public function store(InteractivePackageCreateRequest $request): JsonResponse
    {
        $package = $this->service->create(
            $request->file('file'),
            $request->input('title'),
            $request->user()?->getKey(),
            $request->input('change_note')
        );

        return $this->package($package, 201);
    }

    public function show(InteractiveRequest $request, int $id): JsonResponse
    {
        return $this->package(InteractivePackage::query()->findOrFail($id));
    }

    public function update(InteractivePackageUpdateRequest $request, int $id): JsonResponse
    {
        return $this->package($this->service->rename(InteractivePackage::query()->findOrFail($id), $request->input('title')));
    }

    public function destroy(InteractiveRequest $request, int $id): JsonResponse
    {
        $package = InteractivePackage::query()->findOrFail($id);
        if ($package->topics()->exists()) {
            return response()->json(['success' => false, 'message' => 'Topics use this package; remove them first.'], 409);
        }
        $this->service->delete($package);

        return response()->json(['success' => true, 'data' => null, 'message' => 'Deleted']);
    }

    public function versions(InteractiveRequest $request, int $id): JsonResponse
    {
        $package = InteractivePackage::query()->findOrFail($id);

        return response()->json(['success' => true, 'data' => $package->versions->map(fn (InteractivePackageVersion $v) => $v->summary())->all(), 'message' => 'OK']);
    }

    public function addVersion(InteractiveVersionCreateRequest $request, int $id): JsonResponse
    {
        $package = $this->service->addVersion(
            InteractivePackage::query()->findOrFail($id),
            $request->file('file'),
            $request->user()?->getKey(),
            $request->input('change_note')
        );

        return $this->package($package, 201);
    }

    public function preview(InteractiveRequest $request, int $id): JsonResponse
    {
        $request->validate(['version' => ['nullable', 'integer', 'min:1']]);
        $package = InteractivePackage::query()->findOrFail($id);
        $version = $package->version($request->integer('version') ?: $package->current_version);
        abort_if($version === null, 404);
        $origin = rtrim(trim((string) (config('ulams_uploads.content_origin') ?: config('scorm.content_origin'))), '/');
        if ($origin === '') {
            return response()->json(['success' => false, 'message' => 'The preview needs the content origin.'], 503);
        }
        $version->setRelation('package', $package);

        return response()->json(['success' => true, 'data' => [
            'url' => $origin . '/' . $version->entryPath(),
            'nonce' => bin2hex(random_bytes(16)),
            'version' => $version->version,
            'manifest' => $version->manifest,
        ], 'message' => 'OK']);
    }

    private function package(InteractivePackage $package, int $status = 200): JsonResponse
    {
        $package->loadCount(['versions', 'topics']);

        return response()->json(['success' => true, 'data' => (new InteractivePackageResource($package, true))->resolve(), 'message' => 'OK'], $status);
    }
}
