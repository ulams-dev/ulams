<?php

namespace Ulams\Lti\Listeners;

use Ulams\Courses\Events\TopicFinished;
use Ulams\Lti\Jobs\SendGradeToPlatform;
use Ulams\Lti\Models\LtiGradeTarget;

/**
 * Tool side: when a learner who came from a platform finishes a topic, queue a grade update for
 * every platform that launched them into that course with an AGS score scope.
 */
class QueueGradePassback
{
    public function handle(TopicFinished $event): void
    {
        $courseId = $event->getTopic()->lesson?->course_id;
        if ($courseId === null) {
            return;
        }

        LtiGradeTarget::query()
            ->where('user_id', $event->getUser()->getKey())
            ->where('course_id', $courseId)
            ->pluck('id')
            ->each(fn (int $id) => SendGradeToPlatform::dispatch($id));
    }
}
