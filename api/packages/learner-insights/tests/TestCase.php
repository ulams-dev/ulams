<?php

namespace Ulams\LearnerInsights\Tests;

use Ulams\CourseBuilder\Tests\TestCase as BuilderTestCase;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Database\Seeders\LearnerInsightsPermissionSeeder;
use Ulams\LearnerInsights\UlamsLearnerInsightsServiceProvider;

/** The Course Builder test case (users, Passport, fake driver) with Learner Insights loaded and switched on. */
class TestCase extends BuilderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LearnerInsightsPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsLearnerInsightsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('learner_insights.enabled', true);
        $app['config']->set('cache.default', 'array');
    }

    /** @return array{0:Course,1:Topic} a published course with one active topic */
    protected function courseWithTopic(): array
    {
        $course = Course::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);

        return [$course, $topic];
    }

    /** Public so the journey DSL can enrol a learner. */
    public function learnerFor(Course $course)
    {
        return $this->learner($course);
    }

    protected function learner(Course $course)
    {
        $user = $this->student();
        $user->courses()->attach($course->getKey());

        return $user;
    }

    protected function progress(Topic $topic, $user, int $status = ProgressStatus::IN_PROGRESS, ?int $seconds = null): void
    {
        app(\Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract::class)->updateInTopic($topic, $user, $status, $seconds);
    }

    /** Opens and then completes a topic: TopicFinished only fires on a transition of an existing row. */
    protected function finish(Topic $topic, $user, int $seconds): void
    {
        $this->progress($topic, $user, ProgressStatus::IN_PROGRESS, 0);
        $this->progress($topic, $user, ProgressStatus::COMPLETE, $seconds);
    }
}
