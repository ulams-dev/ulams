<?php

namespace Ulams\Courses\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A learner's progress on a topic was saved (any status, not only completion). Carries ids only,
 * never a `User`, so the notifications package does not turn it into a notification.
 */
class TopicProgressUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $topicId,
        public readonly int $status,
        public readonly ?int $secondsDelta = null,
    ) {
    }
}
