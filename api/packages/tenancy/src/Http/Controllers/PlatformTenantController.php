<?php

namespace Ulams\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tenancy\Http\Controllers\Swagger\PlatformTenantSwagger;
use Ulams\Tenancy\Http\Requests\CreatePlatformTenantRequest;
use Ulams\Tenancy\Http\Requests\DeletePlatformTenantRequest;
use Ulams\Tenancy\Http\Requests\ListPlatformRequest;
use Ulams\Tenancy\Http\Requests\SetPlatformTenantEnvRequest;
use Ulams\Tenancy\Http\Resources\TenantOperationResource;
use Ulams\Tenancy\Http\Resources\TenantResource;
use Ulams\Tenancy\Jobs\DeleteTenantJob;
use Ulams\Tenancy\Jobs\ProvisionTenantJob;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantOperation;
use Ulams\Tenancy\Models\TenantTemplate;
use Ulams\Tenancy\Services\TenantDerivations;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Services\TenantProvisioner;

/**
 * Tenant management over HTTP (ADR 0078). Creation and deletion are queued and answer 202 with an
 * operation to poll; the artisan commands keep working and share `TenantLifecycle`.
 */
class PlatformTenantController extends UlamsBaseController implements PlatformTenantSwagger
{
    public function __construct(private TenantLifecycle $lifecycle, private TenantDerivations $derivations)
    {
    }

    public function index(ListPlatformRequest $request): JsonResponse
    {
        $tenants = Tenant::query()->orderBy('slug')->get();

        return $this->sendResponse(TenantResource::collection($tenants)->resolve($request), __('Tenants'));
    }

    public function show(ListPlatformRequest $request, string $slug): JsonResponse
    {
        return $this->sendResponse((new TenantResource($this->find($slug)))->resolve($request), __('Tenant'));
    }

    public function store(CreatePlatformTenantRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = (string) $data['slug'];

        $busy = $this->activeOperation($slug);
        if ($busy !== null) {
            return $this->conflict('This tenant already has an operation in progress.', $busy);
        }
        $existing = Tenant::query()->firstWhere('slug', $slug);
        if ($existing !== null && $existing->status === Tenant::STATUS_ACTIVE) {
            return $this->sendError("Tenant '{$slug}' already exists.", 409);
        }

        $derivation = null;
        try {
            if (!empty($data['derived_from'])) {
                $derivation = $this->derivations->register($slug, (string) $data['derived_from'], (string) $data['purpose'], (string) $data['reference'], $data['template'] ?? null, (string) ($request->user()->email ?? $request->user()->getKey()));
            }
            $tenant = $this->lifecycle->prepare($slug, [
                'template' => $data['template'] ?? null,
                'name' => $data['name'] ?? null,
                'theme' => $data['theme'] ?? null,
                'accent' => $data['accent'] ?? null,
                'demo' => array_key_exists('demo', $data) && $data['demo'] !== null ? ($data['demo'] ? 'on' : 'off') : null,
            ]);
        } catch (InvalidArgumentException $e) {
            if ($derivation?->wasRecentlyCreated) {
                $derivation->delete();
            }

            return $this->sendError($e->getMessage(), $derivation === null && !empty($data['derived_from']) ? 409 : 422);
        }

        $users = (int) ($data['users'] ?? (!empty($data['template']) ? (TenantTemplate::query()->where('slug', $data['template'])->value('users') ?? 5) : 5));
        $operation = TenantOperation::start(TenantOperation::CREATE, $tenant->slug, TenantProvisioner::STEPS, (int) $request->user()->getKey(), array_filter(['users' => $users, 'template' => $data['template'] ?? null, 'derived_from' => $data['derived_from'] ?? null, 'purpose' => $data['purpose'] ?? null, 'reference' => $data['reference'] ?? null], fn ($v) => $v !== null));
        ProvisionTenantJob::dispatch($operation->id, $users);

        return $this->sendResponse([
            'operation' => (new TenantOperationResource($operation))->resolve($request),
            'tenant' => (new TenantResource($tenant))->resolve($request),
        ], __('Tenant creation queued'), 202);
    }

    public function env(SetPlatformTenantEnvRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->find($slug);
        try {
            $this->lifecycle->applyEnv($tenant, (array) $request->input('set', []), array_values((array) $request->input('unset', [])));
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        return $this->sendResponse((new TenantResource($tenant->refresh()))->resolve($request), __('Tenant settings updated'));
    }

    public function destroy(DeletePlatformTenantRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->find($slug);
        $busy = $this->activeOperation($tenant->slug);
        if ($busy !== null) {
            return $this->conflict('This tenant already has an operation in progress.', $busy);
        }
        $children = $this->derivations->activeOf($tenant->slug);
        if ($children->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => "Tenant '{$tenant->slug}' has tenants created from it; delete them first.", 'code' => 'has_derived_tenants', 'data' => ['derived' => $children->pluck('tenant_slug')->all()]], 409);
        }

        $operation = TenantOperation::start(TenantOperation::DELETE, $tenant->slug, TenantLifecycle::DELETE_STEPS, (int) $request->user()->getKey());
        DeleteTenantJob::dispatch($operation->id);

        return $this->sendResponse(['operation' => (new TenantOperationResource($operation))->resolve($request)], __('Tenant deletion queued'), 202);
    }

    /** The tenants created from this one for a purpose, with the record of who asked. */
    public function derived(ListPlatformRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->find($slug);

        return $this->sendResponse($this->derivations->activeOf($tenant->slug)->map(fn ($d) => [
            'tenant' => $d->tenant_slug, 'template' => $d->template_slug, 'purpose' => $d->purpose, 'reference' => $d->reference,
            'requested_by' => $d->requested_by, 'created_at' => $d->created_at?->toIso8601String(),
        ])->values()->all(), __('Derived tenants'));
    }

    private function find(string $slug): Tenant
    {
        return Tenant::query()->firstWhere('slug', $slug) ?? throw new NotFoundHttpException('Tenant not found.');
    }

    private function activeOperation(string $slug): ?TenantOperation
    {
        return TenantOperation::query()->where('tenant_slug', $slug)->whereIn('status', TenantOperation::ACTIVE)->latest('created_at')->first();
    }

    private function conflict(string $message, TenantOperation $operation): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'operation_in_progress',
            'data' => ['operation' => (new TenantOperationResource($operation))->resolve(request())],
        ], 409);
    }
}
