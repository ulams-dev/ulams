<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Http\PolicyPresenter;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Services\PolicyAccess;
use Ulams\PolicyLifecycle\Services\VariantGenerator;
use Ulams\PolicyLifecycle\Services\VersionApprovalService;

/**
 * The departmental courses of a policy and the approval of its first version.
 *
 * @OA\Get(path="/api/admin/policies/{policy}/variants", operationId="admin.policies.by-id.variants.get", summary="List the department variants of a policy", tags={"Policies"}, security={{"passport": {}}},
 *     description="Each variant carries its stage, read from the Course Builder session: waiting, ingesting, interview, outlining, outline_review, generating, apply_review, applying, applied or failed.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="variants with department, group, session, course and stage", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy"))
 * @OA\Post(path="/api/admin/policies/{policy}/variants/generate", operationId="admin.policies.by-id.variants.generate.post", summary="Generate the department courses of a policy", tags={"Policies"}, security={{"passport": {}}},
 *     description="Starts one Course Builder session per department from the policy file, one after the other, up to the outline review. The owner then reviews, approves and applies each course in the Studio as for any generated course; every lesson and question cites the policy text. Uses AI; the call is refused when AI is switched off.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=false, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", @OA\Property(property="variant_ids", type="array", description="only these departments (default: all that wait)", @OA\Items(type="string"))))),
 *     @OA\Response(response=202, description="the variants requested now", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy"), @OA\Response(response=422, description="Nothing to generate"), @OA\Response(response=503, description="AI is disabled"))
 * @OA\Post(path="/api/admin/policies/{policy}/versions/{number}/approve", operationId="admin.policies.by-id.versions.by-number.approve.post", summary="Approve a policy version", tags={"Policies"}, security={{"passport": {}}},
 *     description="Approves the first version once every department's course is applied and published in the Studio: the courses become mandatory for the department's group, the group is enrolled through a rule on a one-course path, and the version becomes the one learners are trained on. A decision of the owner (or of an admin acting for an absent owner, which the audit records); nothing approves itself.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="number", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="the approved version", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy or version"), @OA\Response(response=409, description="The version is not waiting for approval"), @OA\Response(response=422, description="A department course is not published yet"))
 */
class VariantController extends PolicyApiController
{
    public function __construct(PolicyAccess $access, private VariantGenerator $generator, private VersionApprovalService $approvals)
    {
        parent::__construct($access);
    }

    public function index(Request $request, string $policy): JsonResponse
    {
        $p = $this->policyFor($request, $policy);

        return $this->ok($p->variants->map(fn (PolicyVariant $v) => PolicyPresenter::variant($v, $this->generator))->values());
    }

    public function generate(Request $request, string $policy): JsonResponse
    {
        $p = $this->policyFor($request, $policy);
        $data = $request->validate(['variant_ids' => ['nullable', 'array'], 'variant_ids.*' => ['string']]);
        try {
            $requested = $this->generator->request($p, $data['variant_ids'] ?? null);
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok(collect($requested)->map(fn (PolicyVariant $v) => PolicyPresenter::variant($v->refresh(), $this->generator))->values(), 202);
    }

    public function approve(Request $request, string $policy, int $number): JsonResponse
    {
        $p = $this->policyFor($request, $policy);
        $version = PolicyVersion::query()->where('policy_id', $p->id)->where('number', $number)->firstOrFail();
        try {
            $approved = $this->approvals->approveInitial($p, $version, $request->user());
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok(PolicyPresenter::version($approved));
    }
}
