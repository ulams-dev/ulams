<?php

namespace Ulams\PolicyLifecycle\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Compliance\Models\Assignment;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyReassignment;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * The learner's own side of a policy change.
 *
 * @OA\Get(path="/api/policies/my-changes", operationId="policies.my-changes.get", summary="Why I was given this training again", tags={"Policies"}, security={{"passport": {}}},
 *     description="For each of the caller's assignments that an approved policy change created: the policy, its new version, the source diff in one sentence, whether it is an exam or the course, and the assignment it belongs to. Only the caller's own rows.",
 *     @OA\Response(response=200, description="one row per policy change assignment, newest first", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")))
 */
class LearnerController extends PolicyApiController
{
    public function mine(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $rows = [];
        foreach (PolicyReassignment::query()->where('user_id', $userId)->whereNotNull('assignment_id')->orderByDesc('id')->get() as $row) {
            $rollout = PolicyRollout::query()->find($row->rollout_id);
            $version = $rollout !== null ? PolicyVersion::query()->find($rollout->policy_version_id) : null;
            $policy = $version !== null ? Policy::query()->find($version->policy_id) : null;
            $assignment = Assignment::query()->where('user_id', $userId)->find($row->assignment_id);
            if ($version === null || $policy === null || $assignment === null) {
                continue;
            }
            $rows[] = [
                'assignmentId' => $assignment->getKey(),
                'policy' => ['id' => $policy->id, 'title' => $policy->title],
                'version' => $version->number,
                'kind' => $row->kind,
                'summary' => $version->summary,
                'dueAt' => $assignment->due_at?->toIso8601String(),
                'status' => $assignment->displayStatus(),
            ];
        }

        return $this->ok($rows);
    }
}
