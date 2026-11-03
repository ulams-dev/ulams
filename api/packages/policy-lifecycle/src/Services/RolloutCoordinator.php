<?php

namespace Ulams\PolicyLifecycle\Services;

use Ulams\LivingCourse\Models\Proposal;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * Connects the Living Course apply of each department's proposal to the rollout: a version is rolled out once every
 * department course has been updated, and not at all when one fails (the version goes back to review, nothing below
 * the course updates runs, and approving again resumes it).
 */
final class RolloutCoordinator
{
    public function __construct(private readonly RolloutService $rollouts, private readonly PolicyAudit $audit)
    {
    }

    /** A proposal was applied: its department is done; the last one starts the rollout. */
    public function proposalApplied(Proposal $proposal): void
    {
        $rollout = PolicyRollout::query()->where('proposal_id', $proposal->id)->first();
        if ($rollout === null) {
            return;
        }
        $rollout->forceFill(['status' => PolicyRollout::APPLIED, 'error' => null])->save();
        $this->maybeRun(PolicyVersion::query()->findOrFail($rollout->policy_version_id));
    }

    /** An apply run of a department's session failed: its proposal is open again, and the version returns to review. */
    public function applyFailed(string $sessionId): void
    {
        $rollouts = PolicyRollout::query()->where('status', PolicyRollout::APPLYING)
            ->whereIn('proposal_id', Proposal::query()->where('session_id', $sessionId)->select('id'))->get();
        foreach ($rollouts as $rollout) {
            $proposal = Proposal::query()->find($rollout->proposal_id);
            if ($proposal?->status === 'applied') {
                continue;
            }
            $message = (string) ($proposal?->error ?: 'The update of the course could not be applied.');
            $rollout->forceFill(['status' => PolicyRollout::FAILED, 'error' => mb_substr($message, 0, 1000)])->save();
            $version = PolicyVersion::query()->find($rollout->policy_version_id);
            if ($version !== null && $version->status === PolicyVersion::APPLYING) {
                $version->forceFill(['status' => PolicyVersion::IN_REVIEW])->save();
                $policy = Policy::query()->find($version->policy_id);
                if ($policy !== null) {
                    $this->audit->record('policy.version.submitted', $policy, $version, ['returned_to_review' => true, 'reason' => mb_substr($message, 0, 300)], $sessionId);
                }
            }
        }
    }

    /** Starts the rollout when the version is approved and no department is waiting any more. */
    public function maybeRun(PolicyVersion $version): void
    {
        $version->refresh();
        if ($version->status !== PolicyVersion::APPLYING) {
            return;
        }
        $waiting = PolicyRollout::query()->where('policy_version_id', $version->id)
            ->whereNotIn('status', [PolicyRollout::APPLIED, PolicyRollout::NO_CHANGE])->exists();
        if (!$waiting) {
            $this->rollouts->run($version);
        }
    }
}
