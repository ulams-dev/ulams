<?php

namespace Ulams\Lrs\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A cmi5 AU reported `completed` or `passed` for its own registration through an LRS session
 * token. The course packages listen and complete the topics that use the AU.
 */
class AuCompletionReported
{
    use Dispatchable;

    public function __construct(public readonly int $userId, public readonly int $auId, public readonly string $registration)
    {
    }
}
