<?php

namespace Ulams\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Ulams\Tenancy\Enums\TenancyPermissionsEnum;
use Ulams\Tenancy\Jobs\ProvisionTenantJob;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * Platform API for tenants (ADR 0078): queue the provisioning of a tenant and read its progress.
 * Platform hosts only, switched on with TENANCY_PLATFORM_API, permission `tenancy_manage`.
 *
 * @OA\Post(path="/api/platform/tenants", summary="Provision a tenant in the background (platform hosts only)", tags={"Platform"}, security={{"passport": {}}},
 *     @OA\RequestBody(@OA\JsonContent(type="object")), @OA\Response(response=202, description="queued"), @OA\Response(response=422, description="invalid"))
 * @OA\Get(path="/api/platform/tenants/{slug}", summary="Provisioning progress of a tenant", tags={"Platform"}, security={{"passport": {}}},
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="steps and status"))
 */
class PlatformTenantsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'slug' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:120'],
            'theme' => ['nullable', 'regex:/^[A-Za-z0-9_-]{1,40}$/'],
            'accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        try {
            TenantNaming::assertValidSlug($data['slug']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['slug' => [$e->getMessage()]]], 422);
        }
        $tenant = Tenant::query()->firstWhere('slug', $data['slug']);
        if ($tenant !== null && $tenant->status === Tenant::STATUS_ACTIVE) {
            return response()->json(['success' => false, 'message' => 'That site already exists.', 'errors' => ['slug' => ['That site already exists.']]], 422);
        }
        $tenant ??= new Tenant(TenantNaming::newTenantAttributes($data['slug']));
        $tenant->fill(array_filter(['name' => $data['name'] ?? null, 'theme' => $data['theme'] ?? null, 'accent' => $data['accent'] ?? null], fn ($v) => $v !== null && $v !== ''));
        $tenant->status = Tenant::STATUS_PROVISIONING;
        $tenant->save();

        ProvisionTenantJob::dispatch($tenant->slug);

        return response()->json(['success' => true, 'data' => $this->present($tenant), 'message' => 'Provisioning queued.'], 202);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $this->authorizeManage($request);
        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->present($tenant), 'message' => 'OK']);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(TenancyPermissionsEnum::TENANCY_MANAGE), 403, 'Managing tenants needs the tenancy_manage permission.');
    }

    /** @return array<string,mixed> */
    private function present(Tenant $tenant): array
    {
        $steps = [];
        foreach (TenantProvisioner::STEPS as $step) {
            $steps[] = ['step' => $step, 'state' => $tenant->hasCompleted($step) ? 'done' : 'pending'];
        }

        return [
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'status' => $tenant->status,
            'steps' => $steps,
            'error' => $tenant->last_error,
            'urls' => $tenant->status === Tenant::STATUS_ACTIVE ? ['api' => $tenant->apiUrl(), 'front' => $tenant->frontUrl(), 'admin' => $tenant->adminUrl()] : null,
        ];
    }
}
