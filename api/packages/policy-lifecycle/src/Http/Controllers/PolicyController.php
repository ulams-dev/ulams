<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Http\PolicyPresenter;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Services\DepartmentAcademy;
use Ulams\PolicyLifecycle\Services\PolicyAccess;
use Ulams\PolicyLifecycle\Services\PolicyService;
use Ulams\PolicyLifecycle\Services\VariantGenerator;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Policies of the tenant (ADR 0140). `policy_manage` sees and changes all of them; `policy_owner` the ones the user owns.
 *
 * @OA\Get(path="/api/admin/policies", operationId="admin.policies.get", summary="List policies", tags={"Policies"}, security={{"passport": {}}},
 *     description="All policies for `policy_manage`, the caller's own for `policy_owner`.",
 *     @OA\Response(response=200, description="policies with current and pending version and department count", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 * @OA\Post(path="/api/admin/policies", operationId="admin.policies.post", summary="Create a policy from an uploaded document", tags={"Policies"}, security={{"passport": {}}},
 *     description="Stores the policy file as version 1 (a draft) and one variant per department with its audience group. Nothing is generated until `variants/generate` is called. The policy file is treated as untrusted input.",
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(type="object", required={"title","file","departments"},
 *         @OA\Property(property="title", type="string"),
 *         @OA\Property(property="file", type="string", format="binary", description="Markdown, PDF or DOCX"),
 *         @OA\Property(property="owner_user_id", type="integer", description="only with policy_manage; default the caller"),
 *         @OA\Property(property="minor_due_days", type="integer", default=14),
 *         @OA\Property(property="major_due_days", type="integer", default=30),
 *         @OA\Property(property="brief", type="object", description="totalMinutes, lessonMinutes, language, tone"),
 *         @OA\Property(property="departments", type="array", description="1 to 12 departments", @OA\Items(type="object", required={"label"},
 *             @OA\Property(property="label", type="string"),
 *             @OA\Property(property="group_id", type="integer", nullable=true, description="existing audience group; a new one is created when omitted"),
 *             @OA\Property(property="seniority", type="string", enum={"junior","mid","senior","mixed"}),
 *             @OA\Property(property="priorExperience", type="string", enum={"none","some","expert"}),
 *             @OA\Property(property="totalMinutes", type="integer", nullable=true)))))),
 *     @OA\Response(response=201, description="the policy with its versions and variants", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Validation error or rejected file"))
 * @OA\Get(path="/api/admin/policies/{policy}", operationId="admin.policies.by-id.get", summary="Show a policy", tags={"Policies"}, security={{"passport": {}}},
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="the policy with versions, variants and the generation cost estimate", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy"))
 * @OA\Get(path="/api/admin/policies/{policy}/versions", operationId="admin.policies.by-id.versions.get", summary="List the versions of a policy", tags={"Policies"}, security={{"passport": {}}},
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="versions, oldest first", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy"))
 */
class PolicyController extends PolicyApiController
{
    public function __construct(PolicyAccess $access, private PolicyService $policies, private VariantGenerator $generator, private DepartmentAcademy $academies)
    {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $this->guardUse($request);
        $policies = $this->access->scope(Policy::query()->with(['versions', 'variants'])->latest('created_at'), $request->user())->get();

        return $this->ok($policies->map(fn (Policy $p) => PolicyPresenter::policy($p, $this->generator))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardUse($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'minor_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'major_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'brief' => ['nullable', 'array'],
            'brief.totalMinutes' => ['nullable', 'integer', 'min:5', 'max:2400'],
            'brief.lessonMinutes' => ['nullable', 'integer', 'min:2', 'max:120'],
            'brief.language' => ['nullable', 'string', 'size:2'],
            'brief.tone' => ['nullable', 'string', 'in:friendly,professional,playful,academic'],
            'departments' => ['required', 'array', 'min:1', 'max:' . PolicyService::MAX_DEPARTMENTS],
            'departments.*.label' => ['required', 'string', 'max:120'],
            'departments.*.group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'departments.*.seniority' => ['nullable', 'string', 'in:' . implode(',', PolicyVariant::SENIORITY)],
            'departments.*.priorExperience' => ['nullable', 'string', 'in:' . implode(',', PolicyVariant::EXPERIENCE)],
            'departments.*.totalMinutes' => ['nullable', 'integer', 'min:5', 'max:2400'],
        ]);
        // an owner may only create policies they own
        if (!$this->access->manageAll($request->user())) {
            $data['owner_user_id'] = $request->user()->getAuthIdentifier();
        }
        try {
            $policy = $this->policies->create($request->user(), $data, $request->file('file'));
        } catch (UploadRejected $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]], 'reason' => $e->reason], 422);
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok(PolicyPresenter::policy($policy, $this->generator, true), 201);
    }

    public function show(Request $request, string $policy): JsonResponse
    {
        $p = $this->policyFor($request, $policy);

        return $this->ok(PolicyPresenter::policy($p, $this->generator, true) + ['estimate' => $this->policies->estimate($p), 'academyAvailable' => $this->academies->available($request->user()), 'academyThemes' => DepartmentAcademy::themes()]);
    }

    public function versions(Request $request, string $policy): JsonResponse
    {
        $p = $this->policyFor($request, $policy);

        return $this->ok($p->versions->map(fn (PolicyVersion $v) => PolicyPresenter::version($v))->values());
    }
}
