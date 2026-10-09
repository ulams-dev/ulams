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
use Ulams\Scorm\Services\Contracts\ScormTrackServiceContract;
use Ulams\TopicTypes\Database\Factories\TopicContent\Components\ScormScoHelper;
use Ulams\TopicTypes\Models\TopicContent\ScormSco;
use Ulams\TopicTypes\Tests\TestCase;

class ScormCompletionTest extends TestCase
{
    use DatabaseTransactions;

    private Topic $topic;
    private Course $course;
    private string $scoUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        $sco = ScormScoHelper::getScormSco();
        $this->scoUuid = $sco->uuid;
        $this->course = Course::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $content = ScormSco::query()->create(['value' => $sco->getKey()]);
        $this->topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $this->topic->topicable()->associate($content)->save();
    }

    public function testAPassedScoCompletesTheTopicForAnEnrolledLearner(): void
    {
        Event::fake([TopicFinished::class]);
        $student = $this->learner(enrolled: true);

        $this->track($student, ['cmi.core.lesson_status' => 'incomplete']);
        $this->assertDatabaseMissing('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);

        $this->track($student, ['cmi.core.lesson_status' => 'passed']);

        $this->assertDatabaseHas('course_progress', ['topic_id' => $this->topic->getKey(), 'user_id' => $student->getKey(), 'status' => ProgressStatus::COMPLETE]);
        Event::assertDispatched(TopicFinished::class);
    }

    public function testLearnersWithoutAccessToTheCourseGetNoProgress(): void
    {
        $student = $this->learner(enrolled: false);

        $this->track($student, ['cmi.core.lesson_status' => 'completed']);

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

    private function track($user, array $cmi): void
    {
        app(ScormTrackServiceContract::class)->updateScoTracking($this->scoUuid, $user->getKey(), $cmi);
    }
}
