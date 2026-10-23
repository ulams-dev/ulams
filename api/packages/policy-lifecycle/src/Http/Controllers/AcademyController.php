<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Services\DepartmentAcademy;
use Ulams\PolicyLifecycle\Services\PolicyAccess;

/**
 * An academy of its own for a department (ADR 0141, amendment): a separate tenant created from a template, where the
 * department's reviewed course is applied again. A platform operation: it needs a platform administrator and `TENANCY_NEW_SITES`.
 *
 * @OA\Get(path="/api/admin/policies/{policy}/variants/{variant}/academy", operationId="admin.policies.by-id.variants.by-variant.academy.get", summary="The academy of a department", tags={"Policies"}, security={{"passport": {}}},
 *     description="The request for the department's academy and where it stands: queued, provisioning, transferring, done (with the studio, admin and front addresses of the new academy) or failed (with the reason). `available` says whether this caller may ask for one.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="variant", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="academy (null when none was requested), available, themes", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy or department"))
 * @OA\Post(path="/api/admin/policies/{policy}/variants/{variant}/academy", operationId="admin.policies.by-id.variants.by-variant.academy.post", summary="Create an academy of its own for a department", tags={"Policies"}, security={{"passport": {}}},
 *     description="Queues a new tenant created from a template (the platform records it as created for this department and enforces its limit), styled with the chosen theme preset and accent, and moves the department's builder session there; the author is invited and applies and publishes the course in the academy. Asking again after a failure resumes. The policy, its certificates and its dashboard stay in this tenant.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="variant", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=false, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object",
 *         @OA\Property(property="theme", type="string", description="a theme preset"), @OA\Property(property="accent", type="string", example="#C2552D"),
 *         @OA\Property(property="template", type="string", description="tenant template slug; default the installation's"), @OA\Property(property="name", type="string")))),
 *     @OA\Response(response=202, description="queued", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Not a platform administrator, or new sites are off"), @OA\Response(response=404, description="Unknown policy or department"), @OA\Response(response=409, description="Being created, already done, or the course is not generated yet"), @OA\Response(response=422, description="Invalid theme, accent or template"))
 * @OA\Delete(path="/api/admin/policies/{policy}/variants/{variant}/academy", operationId="admin.policies.by-id.variants.by-variant.academy.delete", summary="Remove the academy of a department", tags={"Policies"}, security={{"passport": {}}},
 *     description="Deletes the academy's tenant with everything in it (database, files, settings). The platform keeps the record of who created it.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="variant", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="removed", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy, department or academy"), @OA\Response(response=409, description="Being created"), @OA\Response(response=502, description="The platform could not remove it"))
 */
class AcademyController extends PolicyApiController
{
    public function __construct(PolicyAccess $access, private DepartmentAcademy $academies)
    {
        parent::__construct($access);
    }

    public function show(Request $request, string $policy, string $variant): JsonResponse
    {
        $v = $this->variantFor($request, $policy, $variant);

        return $this->ok(['academy' => $this->academies->status($v), 'available' => $this->academies->available($request->user()), 'themes' => DepartmentAcademy::themes()]);
    }

    public function store(Request $request, string $policy, string $variant): JsonResponse
    {
        $v = $this->variantFor($request, $policy, $variant);
        $data = $request->validate(['theme' => ['nullable', 'string', 'max:40'], 'accent' => ['nullable', 'string', 'max:7'], 'template' => ['nullable', 'string', 'max:40'], 'name' => ['nullable', 'string', 'max:100']]);
        try {
            $this->academies->start($v->policy, $v, $request->user(), $data);
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok(['academy' => $v->refresh()->academy, 'available' => true, 'themes' => DepartmentAcademy::themes()], 202);
    }

    public function destroy(Request $request, string $policy, string $variant): JsonResponse
    {
        $v = $this->variantFor($request, $policy, $variant);
        try {
            $this->academies->remove($v->policy, $v, $request->user());
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok(['academy' => null]);
    }

    private function variantFor(Request $request, string $policy, string $variant): PolicyVariant
    {
        $p = $this->policyFor($request, $policy);

        return PolicyVariant::query()->where('policy_id', $p->id)->findOrFail($variant);
    }
}
