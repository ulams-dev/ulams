<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Support\Presenter;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Http\PolicyPresenter;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Services\ChangeClassifier;
use Ulams\PolicyLifecycle\Services\ChangeService;
use Ulams\PolicyLifecycle\Services\ChangeSummary;
use Ulams\PolicyLifecycle\Services\PolicyAccess;
use Ulams\PolicyLifecycle\Services\VersionApprovalService;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * The versions of a policy: uploading a change, reviewing it, approving or rejecting it.
 *
 * @OA\Post(path="/api/admin/policies/{policy}/versions", operationId="admin.policies.by-id.versions.post", summary="Upload a new version of a policy", tags={"Policies"}, security={{"passport": {}}},
 *     description="The file becomes the next version, in review, and a new revision of each department's source: Living Course detects what changed and proposes the update of each department's course (one proposal per department, reviewed item by item through the living-course endpoints). The first version must be approved, and no other version may wait for a decision. A file with the same text as the current version is refused.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(type="object", required={"file"}, @OA\Property(property="file", type="string", format="binary", description="Markdown, PDF or DOCX")))),
 *     @OA\Response(response=201, description="the version with its departments' rollouts", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy"), @OA\Response(response=409, description="Not ready for a change, or the same text"), @OA\Response(response=422, description="Rejected file"))
 * @OA\Get(path="/api/admin/policies/{policy}/versions/{number}", operationId="admin.policies.by-id.versions.by-number.get", summary="Review a policy version", tags={"Policies"}, security={{"passport": {}}},
 *     description="The source diff, each department's proposal and where it stands, the suggested change class with its reasons, what still blocks approval, and the pre-flight: how many certificates approving will revoke and how many people it will reassign, per department (`?class=minor|major` previews the other class).",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="number", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="class", in="query", @OA\Schema(type="string", enum={"minor","major"})),
 *     @OA\Response(response=200, description="the version with review details", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy or version"))
 * @OA\Post(path="/api/admin/policies/{policy}/versions/{number}/approve", operationId="admin.policies.by-id.versions.by-number.approve.post", summary="Approve a policy version", tags={"Policies"}, security={{"passport": {}}},
 *     description="First version: works once every department course is applied and published in the Studio; the courses become mandatory for the department's group, the group is enrolled through a rule on a one-course path, and the version becomes the one learners are trained on. A change: every department course is updated from its proposal and, when all are, certificates linked to the previous version are revoked and those people are given the exam (minor) or the course (major) with the policy's due days. The change class is suggested from the proposals; the owner may raise it, and may lower it only with a `reason`. A decision of the owner (or of an admin acting for an absent owner, which the audit records); nothing approves itself, and no AI decides who completes anything.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="number", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=false, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object",
 *         @OA\Property(property="change_class", type="string", enum={"minor","major"}),
 *         @OA\Property(property="reason", type="string", description="required to approve a change as a lower class than suggested"),
 *         @OA\Property(property="overwrite", type="boolean", description="replace edits made in the admin after the last apply")))),
 *     @OA\Response(response=200, description="the version (status applying until every department is updated, then approved)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy or version"), @OA\Response(response=409, description="Not waiting for approval, or the update conflicts with edits"), @OA\Response(response=422, description="Something blocks approval, or a lower class without a reason"))
 * @OA\Post(path="/api/admin/policies/{policy}/versions/{number}/reject", operationId="admin.policies.by-id.versions.by-number.reject.post", summary="Reject a policy version in review", tags={"Policies"}, security={{"passport": {}}},
 *     description="The change is not adopted: its open proposals are rejected, the current version stays in force and nobody is retrained. A corrected file is uploaded as a new version.",
 *     @OA\Parameter(name="policy", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="number", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json", @OA\Schema(type="object", required={"reason"}, @OA\Property(property="reason", type="string")))),
 *     @OA\Response(response=200, description="the rejected version", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="Unknown policy or version"), @OA\Response(response=409, description="Only a change in review can be rejected"))
 */
class VersionController extends PolicyApiController
{
    public function __construct(
        PolicyAccess $access,
        private ChangeService $changes,
        private ChangeClassifier $classifier,
        private ChangeSummary $summaries,
        private VersionApprovalService $approvals,
    ) {
        parent::__construct($access);
    }

    public function store(Request $request, string $policy): JsonResponse
    {
        $p = $this->policyFor($request, $policy);
        $request->validate(['file' => ['required', 'file']]);
        try {
            $version = $this->changes->submit($p, $request->file('file'), $request->user());
        } catch (UploadRejected $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]], 'reason' => $e->reason], 422);
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok($this->detail($p, $version, null), 201);
    }

    public function show(Request $request, string $policy, int $number): JsonResponse
    {
        $p = $this->policyFor($request, $policy);
        $version = $this->versionFor($p, $number);
        $class = $request->query('class');

        return $this->ok($this->detail($p, $version, in_array($class, [PolicyVersion::MINOR, PolicyVersion::MAJOR], true) ? $class : null));
    }

    public function approve(Request $request, string $policy, int $number): JsonResponse
    {
        $p = $this->policyFor($request, $policy);
        $version = $this->versionFor($p, $number);
        $data = $request->validate(['change_class' => ['nullable', 'string', 'in:minor,major'], 'reason' => ['nullable', 'string', 'max:2000'], 'overwrite' => ['nullable', 'boolean']]);
        try {
            $approved = $version->change_class === PolicyVersion::INITIAL
                ? $this->approvals->approveInitial($p, $version, $request->user())
                : $this->changes->approve($p, $version, $request->user(), $data['change_class'] ?? null, $data['reason'] ?? null, $request->boolean('overwrite'));
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok(PolicyPresenter::version($approved));
    }

    public function reject(Request $request, string $policy, int $number): JsonResponse
    {
        $p = $this->policyFor($request, $policy);
        $version = $this->versionFor($p, $number);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        try {
            $rejected = $this->changes->reject($p, $version, $request->user(), $data['reason']);
        } catch (PolicyException $e) {
            return $this->fail($e);
        }

        return $this->ok(PolicyPresenter::version($rejected));
    }

    private function versionFor(Policy $policy, int $number): PolicyVersion
    {
        return PolicyVersion::query()->where('policy_id', $policy->id)->where('number', $number)->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function detail(Policy $policy, PolicyVersion $version, ?string $class): array
    {
        $row = PolicyPresenter::version($version);
        if ($version->change_class === PolicyVersion::INITIAL) {
            return $row + ['blockers' => $version->status === PolicyVersion::DRAFT ? $this->approvals->blockers($policy) : []];
        }
        $rollouts = $this->changes->rollouts($version);
        $suggestion = $this->classifier->suggest($version);
        $first = collect($rollouts)->map(fn (PolicyRollout $r) => $r->proposal_id !== null ? Proposal::query()->find($r->proposal_id) : null)->filter()->first();
        $open = in_array($version->status, [PolicyVersion::IN_REVIEW, PolicyVersion::APPLYING], true);

        return $row + [
            'suggestion' => $suggestion,
            'rollouts' => array_map(function (PolicyRollout $r) {
                $proposal = $r->proposal_id !== null ? Proposal::query()->find($r->proposal_id) : null;

                return [
                    'id' => $r->id,
                    'variantId' => $r->variant_id,
                    'department' => PolicyVariant::query()->find($r->variant_id)?->department_label,
                    'status' => $r->status,
                    'error' => $r->error,
                    'proposal' => $proposal !== null ? Presenter::proposalSummary($proposal) : null,
                    'undecided' => $proposal !== null ? $this->classifier->undecided($proposal) : 0,
                    'rolledOutAt' => $r->rolled_out_at?->toIso8601String(),
                ];
            }, $rollouts),
            'changes' => $this->summaries->of($first !== null && $first->to_revision_id !== null ? Revision::query()->find($first->to_revision_id) : null),
            'blockers' => $open ? $this->changes->blockers($version) : [],
            'preflight' => $open ? $this->changes->preflight($policy, $version, $class ?? ($version->status === PolicyVersion::APPLYING ? $version->change_class : $suggestion['class'])) : null,
        ];
    }
}
