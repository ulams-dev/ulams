<?php

namespace Ulams\TopicTypes\Listeners;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Lrs\Events\AuCompletionReported;
use Ulams\TopicTypes\Models\TopicContent\Cmi5Au;

/**
 * When an AU reports `completed` or `passed` through its LRS session (ADR 0046), every cmi5 topic
 * that uses it and that the learner may attend is marked complete (TopicFinished and the
 * lesson/course checks follow), like CompleteScormTopics does for SCORM (ADR 0018).
 */
class CompleteCmi5Topics
{
    public function __construct(private readonly CourseProgressRepositoryContract $progress)
    {
    }

    public function handle(AuCompletionReported $event): void
    {
        $user = Auth::getProvider()->retrieveById($event->userId);
        if ($user === null) {
            return;
        }

        $topics = Topic::query()
            ->where('topicable_type', Cmi5Au::class)
            ->whereIn('topicable_id', Cmi5Au::query()->where('value', $event->auId)->select('id'))
            ->get();

        foreach ($topics as $topic) {
            // the course policy directly: the LRS request is authenticated by the session token,
            // there is no auth user for the topic and lesson policies
            $course = $topic->lesson?->course;
            if ($course === null || !Gate::forUser($user)->allows('attend', $course)) {
                continue;
            }
            // TopicFinished only fires on a transition from an existing, unfinished row
            if (!$topic->progress()->where('user_id', $event->userId)->exists()) {
                $this->progress->updateInTopic($topic, $user, ProgressStatus::IN_PROGRESS);
            }
            $this->progress->updateInTopic($topic, $user, ProgressStatus::COMPLETE);
        }
    }
}
