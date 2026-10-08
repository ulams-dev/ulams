<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

class CourseApiTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class)) {
            $this->markTestSkipped('Courses package not installed');
        }

        if (!class_exists(\EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider::class)) {
            $this->markTestSkipped('Course-Access package not installed');
        }

        if (!class_exists(\EscolaLms\Scorm\EscolaLmsScormServiceProvider::class)) {
            $this->markTestSkipped('Scorm package not installed');
        }

        $this->seed(CoursesPermissionSeeder::class);
        Config::set('escola_settings.use_database', true);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->mock->reset();
    }

    protected function tearDown(): void
    {
        \EscolaLms\Settings\Models\Config::truncate();
        User::query()->delete();
        Course::query()->delete();
    }

    public function testCourseAssignedTemplateEventListenerWithPackageStatusSetting(): void
    {
        $course = Course::factory()->create([
            'author_id' => $this->user->getKey(),
            'status' => CourseStatusEnum::PUBLISHED,
        ]);

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $student1 = $this->makeStudent();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->post('/api/admin/courses/' . $course->getKey() . '/access/add/', [
            'users' => [$student1->getKey()]
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $student2 = $this->makeStudent();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->post('/api/admin/courses/' . $course->getKey() . '/access/add/', [
            'users' => [$student2->getKey()]
        ])->assertOk();
    }

    public function testAddTutorToChannelWhenPackageIsDisabledAndEnabled(): void
    {
        $course = Course::factory()->make()->toArray();

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses', $course)
            ->assertStatus(201);

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $course = Course::factory()->make()->toArray();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses', $course)
            ->assertStatus(201);
    }

    public function testRemoveTutorFromChannelWhenPackageIsDisabledAndEnabled(): void
    {
        $course = Course::factory()->create();
        $course->authors()->sync($this->makeInstructor()->getKey());;
        $editedCourse = $course->toArray();
        $editedCourse['authors'] = [];

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
            $mock->shouldReceive('removeUserFromChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/courses/' . $course->getKey(),
            $editedCourse
        )->assertStatus(200);

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->andReturn(true);
            $mock->shouldReceive('removeUserFromChannel')->once()->andReturn(true);
        });

        $course->authors()->sync($this->makeInstructor()->getKey());;
        $editedCourse['authors'] = [];

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/courses/' . $course->getKey(),
            $editedCourse
        )->assertStatus(200);
    }

    public function testRemoveUserFromChannelWhenPackageIsDisabledAndEnabled(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create();
        $course->users()->sync([$student->getKey()]);

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
            $mock->shouldReceive('removeUserFromChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses/' . $course->getKey() . '/access/remove/', [
            'users' => [$student->getKey()]
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->andReturn(true);
            $mock->shouldReceive('removeUserFromChannel')->once()->andReturn(true);
        });

        $course->users()->sync([$student->getKey()]);

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses/' . $course->getKey() . '/access/remove/', [
            'users' => [$student->getKey()]
        ])->assertOk();
    }
}
