<?php

namespace Ulams\Tenancy\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The lookup the learner front makes for a host it does not know from its own rules (ADR 0195).
 *
 * @OA\Schema(schema="EntryPointResolution",
 *     @OA\Property(property="tenant", type="string", example="coffee", description="Slug of the academy that serves the host"),
 *     @OA\Property(property="host", type="string"),
 *     @OA\Property(property="kind", type="string", enum={"academy","department","course","role"}),
 *     @OA\Property(property="key", type="string"),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="target", type="object", description="Ids of a group, a course or a role key; never of people",
 *         @OA\Property(property="group_id", type="integer"), @OA\Property(property="course_id", type="integer"), @OA\Property(property="role", type="string")),
 *     @OA\Property(property="theme", type="object", @OA\Property(property="preset", type="string"), @OA\Property(property="accent", type="string"), @OA\Property(property="logo_alt", type="string")),
 *     @OA\Property(property="seo", type="object", @OA\Property(property="canonical", type="string", enum={"tenant","self"}), @OA\Property(property="index", type="boolean"), @OA\Property(property="title", type="string"), @OA\Property(property="description", type="string")),
 *     @OA\Property(property="locale", type="string", nullable=true))
 */
interface EntryPointResolveSwagger
{
    /**
     * @OA\Get(path="/api/platform/entry-points/resolve", operationId="platform.entry-points.resolve.get", summary="The academy and entry point that serve a host (public, platform hosts only)", tags={"Platform"},
     *     @OA\Parameter(name="host", in="query", required=false, description="A front host, without port. Give host, or tenant and key", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tenant", in="query", required=false, description="Academy slug, with key (the /go/{key} fallback on the main host)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="key", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="An active entry point", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/EntryPointResolution"))),
     *     @OA\Response(response=404, description="Unknown, pending, disabled or malformed; also on a tenant host"),
     *     @OA\Response(response=429, description="Too many lookups from this address"))
     */
    public function show(Request $request): JsonResponse;
}
