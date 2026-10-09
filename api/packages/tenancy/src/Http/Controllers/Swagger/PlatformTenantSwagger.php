<?php

namespace Ulams\Tenancy\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Tenancy\Http\Requests\CreatePlatformTenantRequest;
use Ulams\Tenancy\Http\Requests\DeletePlatformTenantRequest;
use Ulams\Tenancy\Http\Requests\ListPlatformRequest;
use Ulams\Tenancy\Http\Requests\SetPlatformTenantEnvRequest;

/**
 * The platform tenant API (ADR 0078): only on a platform host with TENANCY_PLATFORM_API=true (404
 * otherwise), for users with the `platform_admin` permission; scoped tokens need `platform:read|write`.
 *
 * @OA\Schema(schema="PlatformTenant",
 *     @OA\Property(property="slug", type="string", example="coffee"),
 *     @OA\Property(property="name", type="string"), @OA\Property(property="theme", type="string", nullable=true), @OA\Property(property="accent", type="string", nullable=true),
 *     @OA\Property(property="demo", type="boolean"),
 *     @OA\Property(property="status", type="string", enum={"provisioning","active","failed"}),
 *     @OA\Property(property="urls", type="object", @OA\Property(property="api", type="string"), @OA\Property(property="front", type="string"), @OA\Property(property="admin", type="string")),
 *     @OA\Property(property="steps", type="object", description="Finished provisioning steps with their time"),
 *     @OA\Property(property="env_override_keys", type="array", @OA\Items(type="string"), description="Names only, never values"),
 *     @OA\Property(property="last_error", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"), @OA\Property(property="updated_at", type="string", format="date-time"))
 * @OA\Schema(schema="PlatformOperation",
 *     @OA\Property(property="id", type="string", example="01j9z3k8m2x4q7r5t6v8w0y1ab"),
 *     @OA\Property(property="kind", type="string", enum={"create","delete"}),
 *     @OA\Property(property="status", type="string", enum={"queued","running","succeeded","failed"}),
 *     @OA\Property(property="tenant", type="string"),
 *     @OA\Property(property="steps", type="array", @OA\Items(type="object",
 *         @OA\Property(property="name", type="string"), @OA\Property(property="status", type="string", enum={"pending","running","succeeded","skipped","failed"}),
 *         @OA\Property(property="startedAt", type="string", nullable=true), @OA\Property(property="finishedAt", type="string", nullable=true), @OA\Property(property="error", type="string", nullable=true))),
 *     @OA\Property(property="error", type="string", nullable=true), @OA\Property(property="requested_by", type="integer", nullable=true),
 *     @OA\Property(property="created_at", type="string"), @OA\Property(property="started_at", type="string", nullable=true), @OA\Property(property="finished_at", type="string", nullable=true))
 */
interface PlatformTenantSwagger
{
    /**
     * @OA\Get(path="/api/platform/tenants", summary="List the tenants", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Response(response=200, description="Tenants", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PlatformTenant")))),
     *     @OA\Response(response=403, description="Not a platform administrator, or the token lacks platform:read"),
     *     @OA\Response(response=404, description="The platform API is off, or this is a tenant host"))
     */
    public function index(ListPlatformRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/platform/tenants/{slug}", summary="One tenant", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="The tenant", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/PlatformTenant"))),
     *     @OA\Response(response=404, description="Unknown tenant, or the platform API is off"))
     */
    public function show(ListPlatformRequest $request, string $slug): JsonResponse;

    /**
     * @OA\Post(path="/api/platform/tenants", summary="Create a tenant (queued; poll the operation)", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"slug"},
     *         @OA\Property(property="slug", type="string", example="acme", description="2-30 lowercase letters and digits, starting with a letter"),
     *         @OA\Property(property="name", type="string"), @OA\Property(property="theme", type="string", example="coffee"),
     *         @OA\Property(property="accent", type="string", example="#C2552D"), @OA\Property(property="users", type="integer", minimum=0, maximum=50),
     *         @OA\Property(property="demo", type="boolean"))),
     *     @OA\Response(response=202, description="Queued", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object", @OA\Property(property="operation", ref="#/components/schemas/PlatformOperation"), @OA\Property(property="tenant", ref="#/components/schemas/PlatformTenant")))),
     *     @OA\Response(response=409, description="The tenant exists, or an operation for it is in progress"),
     *     @OA\Response(response=422, description="Invalid slug or option"))
     */
    public function store(CreatePlatformTenantRequest $request): JsonResponse;

    /**
     * @OA\Patch(path="/api/platform/tenants/{slug}/env", summary="Override or reset inheritable settings (AI driver, models) of a tenant", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="set", type="object", example={"AI_DRIVER": "fake"}, additionalProperties=@OA\AdditionalProperties(type="string")),
     *         @OA\Property(property="unset", type="array", @OA\Items(type="string")))),
     *     @OA\Response(response=200, description="The tenant", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/PlatformTenant"))),
     *     @OA\Response(response=422, description="A key outside the allow-list"))
     */
    public function env(SetPlatformTenantEnvRequest $request, string $slug): JsonResponse;

    /**
     * @OA\Delete(path="/api/platform/tenants/{slug}", summary="Delete a tenant and all its data (queued; send the slug as confirm)", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"confirm"}, @OA\Property(property="confirm", type="string", description="Must equal the slug"))),
     *     @OA\Response(response=202, description="Queued", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object", @OA\Property(property="operation", ref="#/components/schemas/PlatformOperation")))),
     *     @OA\Response(response=409, description="An operation for this tenant is in progress"),
     *     @OA\Response(response=422, description="confirm does not equal the slug"))
     */
    public function destroy(DeletePlatformTenantRequest $request, string $slug): JsonResponse;
}
