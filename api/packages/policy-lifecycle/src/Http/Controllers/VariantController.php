<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Http\PolicyPresenter;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Services\PolicyAccess;
use Ulams\PolicyLifecycle\Services\VariantGenerator;

/**
 * The departmental courses of a policy.
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
 */
class VariantController extends PolicyApiController
{
    public function __construct(PolicyAccess $access, private VariantGenerator $generator)
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
}
