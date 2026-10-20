<?php

namespace Ulams\Courses\Tests\Repositories;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Events\TopicProgressUpdated;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Models\User;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Courses\Tests\TestCase;

class CourseProgressEventTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function progress_updates_fire_an_event_with_ids_and_the_seconds_delta(): void
    {
        Event::fake([TopicProgressUpdated::class]);
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $user = User::factory()->create();
        $repo = app(CourseProgressRepositoryContract::class);

        $repo->updateInTopic($topic, $user, ProgressStatus::IN_PROGRESS, 30);
        $repo->updateInTopic($topic, $user, ProgressStatus::IN_PROGRESS, 90);

        Event::assertDispatchedTimes(TopicProgressUpdated::class, 2);
        Event::assertDispatched(TopicProgressUpdated::class, fn (TopicProgressUpdated $e) => $e->userId === $user->getKey() && $e->topicId === $topic->getKey() && $e->secondsDelta === 30);
        Event::assertDispatched(TopicProgressUpdated::class, fn (TopicProgressUpdated $e) => $e->secondsDelta === 60);
    }
}
