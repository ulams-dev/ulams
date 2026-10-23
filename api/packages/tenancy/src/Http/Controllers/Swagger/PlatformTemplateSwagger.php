<?php

namespace Ulams\Tenancy\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Tenancy\Http\Requests\ListPlatformRequest;
use Ulams\Tenancy\Http\Requests\SavePlatformTemplateRequest;

/**
 * Tenant templates (ADR 0141, amendment): named starting points for new tenants, applied when a tenant is created
 * (`template` of `POST /api/platform/tenants`, `--template` of `ulams:tenant:create`). Same access as the platform tenant API.
 *
 * @OA\Schema(schema="PlatformTemplate",
 *     @OA\Property(property="slug", type="string", example="policy-academy"),
 *     @OA\Property(property="name", type="string"), @OA\Property(property="theme", type="string", nullable=true), @OA\Property(property="accent", type="string", nullable=true),
 *     @OA\Property(property="demo", type="boolean"), @OA\Property(property="users", type="integer", description="Demo students created with a tenant made from it"),
 *     @OA\Property(property="env_override_keys", type="array", @OA\Items(type="string"), description="Names only, never values"),
 *     @OA\Property(property="created_at", type="string", format="date-time"), @OA\Property(property="updated_at", type="string", format="date-time"))
 */
interface PlatformTemplateSwagger
{
    /**
     * @OA\Get(path="/api/platform/templates", operationId="platform.templates.get", summary="List the tenant templates", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Response(response=200, description="Templates", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PlatformTemplate")))),
     *     @OA\Response(response=403, description="Not a platform administrator, or the token lacks platform:read"),
     *     @OA\Response(response=404, description="The platform API is off, or this is a tenant host"))
     */
    public function index(ListPlatformRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/platform/templates/{slug}", operationId="platform.templates.by-slug.get", summary="One tenant template", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="The template", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/PlatformTemplate"))),
     *     @OA\Response(response=404, description="Unknown template, or the platform API is off"))
     */
    public function show(ListPlatformRequest $request, string $slug): JsonResponse;

    /**
     * @OA\Post(path="/api/platform/templates", operationId="platform.templates.post", summary="Create a tenant template, or change the given fields of an existing one", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"slug"},
     *         @OA\Property(property="slug", type="string", example="policy-academy"), @OA\Property(property="name", type="string"),
     *         @OA\Property(property="theme", type="string", example="coffee"), @OA\Property(property="accent", type="string", example="#C2552D"),
     *         @OA\Property(property="demo", type="boolean"), @OA\Property(property="users", type="integer", minimum=0, maximum=50),
     *         @OA\Property(property="env", type="object", example={"AI_DRIVER": "fake"}, additionalProperties=@OA\AdditionalProperties(type="string"), description="Inheritable settings only"))),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/PlatformTemplate"))),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=422, description="Invalid slug or value"))
     */
    public function store(SavePlatformTemplateRequest $request): JsonResponse;

    /**
     * @OA\Delete(path="/api/platform/templates/{slug}", operationId="platform.templates.by-slug.delete", summary="Remove a tenant template (tenants made from it are untouched)", tags={"Platform"}, security={{"passport": {}}},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Removed", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object", @OA\Property(property="slug", type="string")))),
     *     @OA\Response(response=404, description="Unknown template, or the platform API is off"))
     */
    public function destroy(ListPlatformRequest $request, string $slug): JsonResponse;
}
