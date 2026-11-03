<?php

namespace Ulams\PolicyLifecycle\Listeners;

use Ulams\CourseBuilder\Events\BuilderStageFinished;
use Ulams\LivingCourse\Events\ProposalApplied;
use Ulams\PolicyLifecycle\Services\RolloutCoordinator;

/** Feeds the end of each department's Living Course apply into the rollout of the policy version (see RolloutCoordinator). */
final class TrackRollouts
{
    public function __construct(private readonly RolloutCoordinator $coordinator)
    {
    }

    public function applied(ProposalApplied $event): void
    {
        $this->safely(fn () => $this->coordinator->proposalApplied($event->proposal));
    }

    public function stageFinished(BuilderStageFinished $event): void
    {
        if ($event->stage !== null || $event->runKind !== 'apply' || $event->outcome === 'finished') {
            return;
        }
        $this->safely(fn () => $this->coordinator->applyFailed($event->sessionId));
    }

    /** A failure here must never fail the builder run that has just ended; approving again resumes the version. */
    private function safely(callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
