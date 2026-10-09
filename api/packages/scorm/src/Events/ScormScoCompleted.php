<?php

namespace Ulams\Scorm\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A learner's tracking of a SCO reached `completed` or `passed` for the first time.
 */
class ScormScoCompleted
{
    use Dispatchable;

    public function __construct(public readonly int $userId, public readonly int $scoId)
    {
    }
}
