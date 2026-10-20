<?php

namespace Ulams\LearnerInsights\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per status transition, never on a re-evaluation with the same status. Ids and reason
 * arrays only, no `User`: the notifications package must not turn a status into a notification
 * by accident (ADR 0058).
 */
class LearnerAtRisk
{
    use Dispatchable;

    public function __construct(public readonly int $userId, public readonly int $courseId, public readonly array $reasons)
    {
    }
}
