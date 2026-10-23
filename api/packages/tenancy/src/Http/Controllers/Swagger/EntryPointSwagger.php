<?php

namespace Ulams\Tenancy\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Tenancy\Http\Requests\EntryPointReadRequest;
use Ulams\Tenancy\Http\Requests\EntryPointRequest;

/**
 * Branded entry points of the academy (ADR 0195): extra front hosts that aim at a department, a course or a
 * role. Permission `entry_points_manage`; scoped tokens need `settings:read|write`. An entry point never
 * grants access and never joins a learner to a group.
 *
 * @OA\Schema(schema="EntryPoint",
 *     @OA\Property(property="id", type="string", example="01j9z3k8m2x4q7r5t6v8w0y1ab"),
 *     @OA\Property(property="host", type="string", example="sales.academy.example.org", description="Fixed after creation"),
 *     @OA\Property(property="key", type="string", example="sales", description="Short slug, unique in the academy; the host-less fallback is /go/{key} on the main host. Fixed after creation"),
 *     @OA\Property(property="kind", type="string", enum={"academy","department","course","role"}, description="Fixed after creation"),
 *     @OA\Property(property="target", type="object", description="department: group_id; course: course_id; role: role; academy: empty",
 *         @OA\Property(property="group_id", type="integer"), @OA\Property(property="course_id", type="integer"), @OA\Property(property="role", type="string")),
 *     @OA\Property(property="label", type="string", description="Shown in the header and the host switcher"),
 *     @OA\Property(property="theme", type="object", description="Overrides of the academy theme on this host",
 *         @OA\Property(property="preset", type="string"), @OA\Property(property="accent", type="string", example="#c2552d"), @OA\Property(property="logo_alt", type="string")),
 *     @OA\Property(property="seo", type="object",
 *         @OA\Property(property="canonical", type="string", enum={"tenant","self"}, description="tenant: the pages point at the main host; self: only for a course entry point"),
 *         @OA\Property(property="index", type="boolean", description="false adds noindex; the default is false for department and role, true for academy and course"),
 *         @OA\Property(property="title", type="string"), @OA\Property(property="description", type="string")),
 *     @OA\Property(property="locale", type="string", nullable=true, example="pl"),
 *     @OA\Property(property="status", type="string", enum={"pending_dns","active","disabled"}, description="pending_dns: the host is not verified yet; disabled: the front, the trusted origins and the return URLs stop serving it at once"),
 *     @OA\Property(property="url", type="string", format="uri"),
 *     @OA\Property(property="go_url", type="string", format="uri", description="The same entry on the main host, without DNS"),
 *     @OA\Property(property="dns", type="object", nullable=true, description="What to publish for a custom domain while it is pending_dns",
 *         @OA\Property(property="txt_name", type="string"), @OA\Property(property="txt_value", type="string"), @OA\Property(property="cname_target", type="string", nullable=true)),
 *     @OA\Property(property="verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"), @OA\Property(property="updated_at", type="string", format="date-time"))
 * @OA\Schema(schema="EntryPointCreate", required={"host","key","kind","label"},
 *     @OA\Property(property="host", type="string", example="sales.academy.example.org", description="A DNS name: no address, port or path; hosts under a platform suffix must be {slug}-name.suffix"),
 *     @OA\Property(property="key", type="string", example="sales", description="Lower case letters, digits and hyphens, up to 40; the key main is reserved (it leaves an entry point)"),
 *     @OA\Property(property="kind", type="string", enum={"academy","department","course","role"}),
 *     @OA\Property(property="target", type="object", description="department: {group_id}; course: {course_id}; role: {role}; academy: omit",
 *         @OA\Property(property="group_id", type="integer"), @OA\Property(property="course_id", type="integer"), @OA\Property(property="role", type="string")),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="theme", type="object", @OA\Property(property="preset", type="string"), @OA\Property(property="accent", type="string"), @OA\Property(property="logo_alt", type="string")),
 *     @OA\Property(property="seo", type="object", @OA\Property(property="canonical", type="string", enum={"tenant","self"}), @OA\Property(property="index", type="boolean"), @OA\Property(property="title", type="string"), @OA\Property(property="description", type="string")),
 *     @OA\Property(property="locale", type="string", nullable=true))
 * @OA\Schema(schema="EntryPointUpdate",
 *     @OA\Property(property="target", type="object", description="Same shape as at creation for the entry point's kind",
 *         @OA\Property(property="group_id", type="integer"), @OA\Property(property="course_id", type="integer"), @OA\Property(property="role", type="string")),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="theme", type="object", nullable=true, @OA\Property(property="preset", type="string"), @OA\Property(property="accent", type="string"), @OA\Property(property="logo_alt", type="string")),
 *     @OA\Property(property="seo", type="object", nullable=true, @OA\Property(property="canonical", type="string", enum={"tenant","self"}), @OA\Property(property="index", type="boolean"), @OA\Property(property="title", type="string"), @OA\Property(property="description", type="string")),
 *     @OA\Property(property="locale", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"active","disabled"}, description="active needs a verified host"))
 * @OA\Schema(schema="EntryPointAudit",
 *     @OA\Property(property="id", type="integer"), @OA\Property(property="entry_point_id", type="string", nullable=true),
 *     @OA\Property(property="action", type="string", enum={"created","updated","enabled","disabled","deleted"}),
 *     @OA\Property(property="host", type="string"), @OA\Property(property="actor_id", type="integer", nullable=true), @OA\Property(property="actor_email", type="string", nullable=true),
 *     @OA\Property(property="changes", type="object"), @OA\Property(property="created_at", type="string", format="date-time"))
 */
interface EntryPointSwagger
{
    /**
     * @OA\Get(path="/api/admin/entry-points", operationId="admin.entry-points.list", summary="Entry points of this academy", tags={"Admin Entry points"}, security={{"passport": {}}},
     *     @OA\Response(response=200, description="Entry points", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EntryPoint")))),
     *     @OA\Response(response=403, description="Needs entry_points_manage"),
     *     @OA\Response(response=503, description="The platform did not answer; try again"))
     */
    public function index(EntryPointReadRequest $request): JsonResponse;

    /**
     * @OA\Post(path="/api/admin/entry-points", operationId="admin.entry-points.create", summary="Add an entry point (a pending host until its DNS record is verified)", tags={"Admin Entry points"}, security={{"passport": {}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EntryPointCreate")),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/EntryPoint"))),
     *     @OA\Response(response=403, description="Needs entry_points_manage"),
     *     @OA\Response(response=422, description="Invalid input, a taken host or key, a target that does not exist, or the academy is at its limit"))
     */
    public function store(EntryPointRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/admin/entry-points/{entryPoint}", operationId="admin.entry-points.by-id.get", summary="One entry point", tags={"Admin Entry points"}, security={{"passport": {}}},
     *     @OA\Parameter(name="entryPoint", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="The entry point", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/EntryPoint"))),
     *     @OA\Response(response=404, description="Not found in this academy"))
     */
    public function show(EntryPointReadRequest $request, string $entryPoint): JsonResponse;

    /**
     * @OA\Patch(path="/api/admin/entry-points/{entryPoint}", operationId="admin.entry-points.by-id.update", summary="Change the label, target, theme, SEO or status of an entry point", tags={"Admin Entry points"}, security={{"passport": {}}},
     *     @OA\Parameter(name="entryPoint", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EntryPointUpdate")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/EntryPoint"))),
     *     @OA\Response(response=404, description="Not found in this academy"),
     *     @OA\Response(response=422, description="Invalid input, or activating a host that is not verified"))
     */
    public function update(EntryPointRequest $request, string $entryPoint): JsonResponse;

    /**
     * @OA\Delete(path="/api/admin/entry-points/{entryPoint}", operationId="admin.entry-points.by-id.delete", summary="Delete an entry point (its host stops serving at once)", tags={"Admin Entry points"}, security={{"passport": {}}},
     *     @OA\Parameter(name="entryPoint", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=404, description="Not found in this academy"))
     */
    public function destroy(EntryPointReadRequest $request, string $entryPoint): JsonResponse;

    /**
     * @OA\Get(path="/api/admin/entry-points/audit", operationId="admin.entry-points.audit.list", summary="Who changed which entry point, newest first", tags={"Admin Entry points"}, security={{"passport": {}}},
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=100, maximum=500)),
     *     @OA\Response(response=200, description="Audit entries", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EntryPointAudit")))),
     *     @OA\Response(response=403, description="Needs entry_points_manage"))
     */
    public function audit(EntryPointReadRequest $request): JsonResponse;
}
