<?php

namespace Ulams\TopicTypes\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Events\TopicFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Lrs\Events\AuCompletionReported;
use Ulams\TopicTypes\Database\Factories\TopicContent\Components\Cmi5AuHelper;
use Ulams\TopicTypes\Models\TopicContent\Cmi5Au;
use Ulams\TopicTypes\Tests\TestCase;

class Cmi5CompletionTest extends TestCase
{
    use DatabaseTransactions;

    private Topic $topic;
    private Course $course;
    private int $auId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        $this->auId = Cmi5AuHelper::getCmi5Au()->getKey();
        $this->course = Course::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $content = Cmi5Au::query()->create(['value' => $this->auId]);
        $this->topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $this->topic->topicable()->associate($content)->save();
    }

    public function testACompletedAuCompletesTheTopicForAnEnrolledLearner(): void
    {
        Event::fake([TopicFinished::class]);
        $student = $this->learner(enrolled: true);

        AuCompletionReported::dispatch($student->getKey(), $this->auId, 'a2cce8c8-cead-4869-84a2-196af4cc8fa9');

        $this->assertDatabaseHas('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);
        Event::assertDispatched(TopicFinished::class);
    }

    public function testLearnersWithoutAccessToTheCourseGetNoProgress(): void
    {
        $student = $this->learner(enrolled: false);

        AuCompletionReported::dispatch($student->getKey(), $this->auId, 'a2cce8c8-cead-4869-84a2-196af4cc8fa9');

        $this->assertDatabaseMissing('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey()]);
    }

    public function testAnotherAuLeavesTheTopicOpen(): void
    {
        $student = $this->learner(enrolled: true);

        AuCompletionReported::dispatch($student->getKey(), $this->auId + 1000, 'a2cce8c8-cead-4869-84a2-196af4cc8fa9');

        $this->assertDatabaseMissing('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey()]);
    }

    private function learner(bool $enrolled)
    {
        $user = config('auth.providers.users.model')::factory()->create();
        $user->guard_name = 'api';
        $user->assignRole('student');
        if ($enrolled) {
            $this->course->users()->attach($user->getKey());
        }

        return $user;
    }
}
