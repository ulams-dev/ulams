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
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Services\TenantProvisioner;

/**
 * Tenant management over HTTP (ADR 0078). Creation and deletion are queued and answer 202 with an
 * operation to poll; the artisan commands keep working and share `TenantLifecycle`.
 */
class PlatformTenantController extends UlamsBaseController implements PlatformTenantSwagger
{
    public function __construct(private TenantLifecycle $lifecycle, private TenantCommandRunnerContract $runner)
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

        $ttl = isset($data['ttl_days']) ? (int) $data['ttl_days'] : null;
        $group = $ttl !== null ? (string) $data['group'] : null;
        if ($existing === null && $ttl !== null) {
            $cap = (int) config('ulams_tenancy.preview_cap', 5);
            $live = Tenant::query()->where('preview_group', $group)->whereNotNull('expires_at')->where('expires_at', '>', now())->count();
            if ($live >= $cap) {
                return response()->json(['success' => false, 'message' => "At most {$cap} preview academies per repository; delete one or wait for it to expire.", 'code' => 'preview_limit'], 409);
            }
        }

        try {
            $tenant = $this->lifecycle->prepare($slug, [
                'name' => $data['name'] ?? null,
                'theme' => $data['theme'] ?? null,
                'accent' => $data['accent'] ?? null,
                'demo' => array_key_exists('demo', $data) && $data['demo'] !== null ? ($data['demo'] ? 'on' : 'off') : null,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        if ($ttl !== null) {
            $tenant->forceFill(['expires_at' => now()->addDays($ttl), 'preview_group' => $group])->save();
        }

        $users = (int) ($data['users'] ?? 5);
        $operation = TenantOperation::start(TenantOperation::CREATE, $tenant->slug, TenantProvisioner::STEPS, (int) $request->user()->getKey(), ['users' => $users]);
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

    /**
     * A one-day administrator token for a preview academy (kind `cli`, so it may approve its own imports), minted inside the
     * tenant. Only throwaway tenants have one: a platform token never becomes an administrator of a real academy this way.
     */
    public function token(ListPlatformRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->find($slug);
        if (!$tenant->isPreview()) {
            return $this->sendError('Only preview academies (created with ttl_days) hand out a token.', 409);
        }
        if ($tenant->status !== Tenant::STATUS_ACTIVE || $tenant->expires_at->isPast()) {
            return $this->sendError('This preview academy is not active.', 409);
        }
        try {
            $output = $this->runner->run($tenant->api_host, ['ulams:tenant:preview-token', '--no-interaction']);
        } catch (\Throwable $e) {
            report($e);

            return $this->sendError('The token could not be created.', 500);
        }
        foreach (array_reverse(preg_split('/\R/', trim($output)) ?: []) as $line) {
            $json = json_decode($line, true);
            if (is_array($json) && isset($json['token'])) {
                return $this->sendResponse(['token' => $json['token'], 'expires_at' => $json['expires_at'] ?? null, 'scopes' => $json['scopes'] ?? [], 'urls' => ['api' => $tenant->apiUrl(), 'front' => $tenant->frontUrl(), 'admin' => $tenant->adminUrl()]], __('Preview token'));
            }
        }

        return $this->sendError('The token could not be created.', 500);
    }

    public function destroy(DeletePlatformTenantRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->find($slug);
        $busy = $this->activeOperation($tenant->slug);
        if ($busy !== null) {
            return $this->conflict('This tenant already has an operation in progress.', $busy);
        }

        $operation = TenantOperation::start(TenantOperation::DELETE, $tenant->slug, TenantLifecycle::DELETE_STEPS, (int) $request->user()->getKey());
        DeleteTenantJob::dispatch($operation->id);

        return $this->sendResponse(['operation' => (new TenantOperationResource($operation))->resolve($request)], __('Tenant deletion queued'), 202);
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
