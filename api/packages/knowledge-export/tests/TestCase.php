<?php

namespace Ulams\KnowledgeExport\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Ai\UlamsAiServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\Courses\AuthServiceProvider as CoursesAuthServiceProvider;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\Models\User;
use Ulams\KnowledgeExport\Database\Seeders\KnowledgePermissionSeeder;
use Ulams\KnowledgeExport\UlamsKnowledgeExportServiceProvider;
use Ulams\TopicTypes\Models\TopicContent\RichText;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        Role::findOrCreate(UserRole::ADMIN, 'api');
        Role::findOrCreate(UserRole::STUDENT, 'api');
        $this->seed(KnowledgePermissionSeeder::class);
        Storage::fake(config('filesystems.default'));
        config(['knowledge_export.base_url' => 'https://academy.test']);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            CoursesAuthServiceProvider::class,
            UlamsAiServiceProvider::class,
            UlamsCourseBuilderServiceProvider::class,
            UlamsKnowledgeExportServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function admin(): User
    {
        return $this->makeAdmin();
    }

    protected function student(): User
    {
        return $this->makeStudent();
    }

    protected function publishedCourse(array $data = []): Course
    {
        return Course::factory()->create($data + [
            'status' => CourseStatusEnum::PUBLISHED, 'active_from' => null, 'active_to' => null,
            'title' => 'Latte art', 'subtitle' => '', 'description' => '',
        ]);
    }

    protected function lesson(Course $course, array $data = []): Lesson
    {
        return Lesson::factory()->create($data + ['course_id' => $course->getKey(), 'active' => true, 'title' => 'Milk', 'order' => 1]);
    }

    protected function richTextTopic(Lesson $lesson, string $markdown, array $data = []): Topic
    {
        $content = RichText::factory()->create(['value' => $markdown]);

        return Topic::factory()->create($data + [
            'lesson_id' => $lesson->getKey(), 'active' => true, 'title' => 'Steaming', 'order' => 1,
            'topicable_type' => RichText::class, 'topicable_id' => $content->getKey(),
        ]);
    }
}
