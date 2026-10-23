<?php

namespace Ulams\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tenancy\Http\Controllers\Swagger\PlatformTemplateSwagger;
use Ulams\Tenancy\Http\Requests\ListPlatformRequest;
use Ulams\Tenancy\Http\Requests\SavePlatformTemplateRequest;
use Ulams\Tenancy\Http\Resources\TenantTemplateResource;
use Ulams\Tenancy\Models\TenantTemplate;
use Ulams\Tenancy\Services\TenantTemplates;

/** Tenant templates over HTTP (ADR 0141, amendment): the starting points of new tenants. Platform administrators only. */
class PlatformTemplateController extends UlamsBaseController implements PlatformTemplateSwagger
{
    public function __construct(private TenantTemplates $templates)
    {
    }

    public function index(ListPlatformRequest $request): JsonResponse
    {
        return $this->sendResponse(TenantTemplateResource::collection(TenantTemplate::query()->orderBy('slug')->get())->resolve($request), __('Tenant templates'));
    }

    public function show(ListPlatformRequest $request, string $slug): JsonResponse
    {
        return $this->sendResponse((new TenantTemplateResource($this->find($slug)))->resolve($request), __('Tenant template'));
    }

    public function store(SavePlatformTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $existed = $this->templates->find((string) $data['slug']) !== null;
        try {
            $template = $this->templates->save((string) $data['slug'], [
                'name' => $data['name'] ?? null, 'theme' => $data['theme'] ?? null, 'accent' => $data['accent'] ?? null,
                'demo' => $data['demo'] ?? null, 'users' => $data['users'] ?? null, 'env' => $data['env'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        return $this->sendResponse((new TenantTemplateResource($template))->resolve($request), $existed ? __('Tenant template updated') : __('Tenant template created'), $existed ? 200 : 201);
    }

    public function destroy(ListPlatformRequest $request, string $slug): JsonResponse
    {
        $this->find($slug);
        $this->templates->delete($slug);

        return $this->sendResponse(['slug' => $slug], __('Tenant template removed'));
    }

    private function find(string $slug): TenantTemplate
    {
        return $this->templates->find($slug) ?? throw new NotFoundHttpException('Tenant template not found.');
    }
}
