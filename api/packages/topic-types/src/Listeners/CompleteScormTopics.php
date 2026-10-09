<?php

namespace Ulams\TopicTypes\Listeners;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Scorm\Events\ScormScoCompleted;
use Ulams\TopicTypes\Models\TopicContent\ScormSco;

/**
 * When a learner completes or passes a SCO, every SCORM topic that uses it and that the learner
 * may attend is marked complete (TopicFinished and the lesson/course checks follow).
 */
class CompleteScormTopics
{
    public function __construct(private readonly CourseProgressRepositoryContract $progress)
    {
    }

    public function handle(ScormScoCompleted $event): void
    {
        $user = Auth::getProvider()->retrieveById($event->userId);
        if ($user === null) {
            return;
        }

        $topics = Topic::query()
            ->where('topicable_type', ScormSco::class)
            ->whereIn('topicable_id', ScormSco::query()->where('value', $event->scoId)->select('id'))
            ->get();

        foreach ($topics as $topic) {
            // the course policy directly: the topic and lesson policies re-check with the current
            // auth user, and tracking requests (content-origin token) have none
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
