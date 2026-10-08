<?php

namespace Ulams\Lti\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Courses\AuthServiceProvider as CoursesAuthServiceProvider;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Lti\Database\Seeders\LtiPermissionSeeder;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\UlamsLtiServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(LtiPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsCourseServiceProvider::class,
            CoursesAuthServiceProvider::class,
            UlamsCourseAccessServiceProvider::class,
            UlamsTopicTypesServiceProvider::class,
            UlamsLtiServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('app.url', 'https://lms.example.test');
        $app['config']->set('app.frontend_url', 'https://app.example.test');
    }

    /**
     * A published course with one lesson and one LTI link topic for `$tool`.
     *
     * @return array{0: Course, 1: Lesson, 2: Topic}
     */
    protected function courseWithLink(LtiTool $tool, array $link = []): array
    {
        $course = Course::factory()->create(['status' => 'published']);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $content = LtiLink::query()->create(['lti_tool_id' => $tool->getKey()] + $link);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $topic->topicable()->associate($content)->save();

        return [$course, $lesson, $topic->refresh()];
    }

    protected function enrol(User $user, Course $course): void
    {
        $course->users()->syncWithoutDetaching([$user->getKey()]);
    }
}
