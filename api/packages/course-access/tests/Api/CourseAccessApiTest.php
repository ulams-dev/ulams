<?php

namespace EscolaLms\CourseAccess\Tests\Api;

use EscolaLms\Auth\Models\Group;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Tests\Models\User;
use EscolaLms\CourseAccess\Tests\TestCase;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Events\CourseAccessStarted;
use EscolaLms\Courses\Events\CourseFinished;
use EscolaLms\CourseAccess\Http\Resources\UserGroupResource;
use EscolaLms\CourseAccess\Models\Course;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class CourseAccessApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = $this->makeInstructor();
        $this->course = Course::factory()->create([
            'status' => CourseStatusEnum::PUBLISHED,
        ]);
        $this->course->authors()->sync($this->user);

        Notification::fake();
    }

    public function testAccessList(): void
    {
        $student = User::factory()->create();
        $group = Group::factory()->create();

        $this->course->users()->sync([$student->getKey()]);
        $this->course->groups()->sync([$group->getKey()]);

        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/courses/' . $this->course->id . '/access');

        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'users' => [
                [
                    'id' => $student->id,
                    'email' => $student->email,
                    'name' => $student->name,
                ]
            ],
            'groups' => [
                UserGroupResource::make($group)->toArray(null),
            ],
        ]);
    }

    public function testSetAccess(): void
    {
        $student = User::factory()->create();
        $group = Group::factory()->create();

        $this->course->users()->sync([$student->getKey()]);
        $this->course->groups()->sync([$group->getKey()]);

        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/courses/' . $this->course->id . '/access');

        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'users' => [
                [
                    'id' => $student->id,
                    'email' => $student->email,
                    'name' => $student->name,
                ]
            ],
            'groups' => [
                UserGroupResource::make($group)->toArray(null),
            ],
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->post('/api/admin/courses/' . $this->course->id . '/access/set', [
                'users' => [],
                'groups' => [],
            ]);
        $this->response->assertOk();
        $this->response->assertJsonMissing([
            'id' => $student->id,
            'email' => $student->email,
            'name' => $student->name,
        ]);
        $this->response->assertJsonMissing([
            'id' => $group->id,
            'name' => $group->name,
        ]);
    }

    public function testAddUserAccess(): void
    {
        Event::fake();
        $student = User::factory()->create();

        $this->assertUserCanNotReadProgram($student, $this->course);

        $this->response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/courses/' . $this->course->id . '/access/add/', [
                'users' => [$student->getKey()],
            ]);

        $this->response->assertOk();
        Event::assertDispatched(CourseAccessStarted::class);
        $this->assertUserCanReadProgram($student, $this->course);
    }

    public function testRemoveUserAccess(): void
    {
        Event::fake([CourseFinished::class]);
        $student = User::factory()->create();
        $this->course->users()->sync([$student->getKey()]);

        $this->assertUserCanReadProgram($student, $this->course);

        $this->response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/courses/' . $this->course->id . '/access/remove/', [
                'users' => [$student->getKey()],
            ]);

        $this->response->assertOk();
        Event::assertDispatched(CourseFinished::class);
        $this->assertUserCanNotReadProgram($student, $this->course);
    }

    public function testAddGroupAccess(): void
    {
        $student = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->sync([$student->getKey()]);

        $this->assertUserCanNotReadProgram($student, $this->course);

        $this->response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/courses/' . $this->course->id . '/access/add/', [
                'groups' => [$group->getKey()],
            ]);

        $this->response->assertOk();

        $this->assertUserCanReadProgram($student, $this->course);
    }

    public function testRemoveGroupAccess(): void
    {
        $student = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->sync([$student->getKey()]);
        $this->course->groups()->sync([$group->getKey()]);

        $this->assertUserCanReadProgram($student, $this->course);

        $this->response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/courses/' . $this->course->id . '/access/remove/', [
                'groups' => [$group->getKey()],
            ]);

        $this->response->assertOk();

        $this->assertUserCanNotReadProgram($student, $this->course);
    }

    public function testAccessToPublishedCourse(): void
    {
        $student = User::factory()->create();

        $unactivatedCourse = Course::factory()->create([
            'status' => CourseStatusEnum::PUBLISHED_UNACTIVATED,
        ]);

        $unactivatedCourse->users()->sync($student->getKey());

        $this->actingAs($student, 'api')->getJson('/api/courses/' . $unactivatedCourse->getKey() . '/program')
            ->assertJson([
                'success' => false,
                'message' => __('Course is not activated yet.'),
            ]);

        $publishedInFuture = Course::factory()->create([
            'status' => CourseStatusEnum::PUBLISHED,
            'active_from' => now()->addHour(),
            'active_to' => now()->addDay(),
        ]);

        $publishedInFuture->users()->sync($student->getKey());

        $this->actingAs($student, 'api')->getJson('/api/courses/' . $publishedInFuture->getKey() . '/program')
            ->assertJson([
                'success' => false,
                'message' => __('Course is not activated yet.'),
            ]);

        $activeCourse = Course::factory()->create([
            'status' => CourseStatusEnum::PUBLISHED,
            'active_from' => now()->addHour(),
            'active_to' => now()->addDay(),
        ]);

        $activeCourse->users()->sync($student->getKey());

        $activeCourse->update([
            'status' => CourseStatusEnum::PUBLISHED,
            'active_from' => now()->subHour(),
            'active_to' => now()->addDay(),
        ]);

        $this->assertUserCanReadProgram($student, $activeCourse);
    }

    public function testGetMyCourseIds(): void
    {
        $courses = Course::factory()->count(10)->create();
        $student = $this->makeStudent();

        $this->actingAs($student, 'api')->getJson('api/courses/my')
            ->assertOk()
            ->assertJsonCount(0, 'data.ids');

        $courses->get(2)->users()->sync($student);

        $parentGroup = Group::factory()->create();
        $childGroup = Group::factory()
            ->state(['parent_id' => $parentGroup->getKey()])
            ->create();
        $parentGroup->users()->sync($student);
        $courses->get(4)->groups()->sync($childGroup);

        $otherGroup = Group::factory()->create();
        $otherGroup->users()->sync($student);
        $courses->get(6)->groups()->sync($otherGroup);

        $this->actingAs($student, 'api')->getJson('api/courses/my')
            ->assertOk()
            ->assertJsonFragment( ['ids' => [
                $courses->get(2)->getKey(),
                $courses->get(4)->getKey(),
                $courses->get(6)->getKey(),
            ]]);
    }


    public function testGetMyCourseIdsFiltered(): void
    {
        $courses = Course::factory()->count(10)->create();
        $student = $this->makeStudent();

        $this->actingAs($student, 'api')->getJson('api/courses/my')
            ->assertOk()
            ->assertJsonCount(0, 'data.ids');

        $courses->get(2)->users()->sync([$student->getKey() => ['end_date' => Carbon::now()->subDay()]]);
        $courses->get(4)->users()->sync([$student->getKey() => ['end_date' => Carbon::now()->addDay()]]);
        $courses->get(6)->users()->sync($student);

        $this->actingAs($student, 'api')->getJson('api/courses/my?active=1')
            ->assertOk()
            ->assertJsonFragment(['ids' => [
                $courses->get(4)->getKey(),
                $courses->get(6)->getKey(),
            ]])
            ->assertJsonMissing(['ids' => [
                $courses->get(2)->getKey(),
            ]]);
    }

    private function assertUserCanReadProgram(User $user, Course $course): void
    {
        $this->actingAs($user, 'api')->getJson('/api/courses/' . $course->id . '/program')
            ->assertOk();
    }

    private function assertUserCanNotReadProgram(User $user, Course $course): void
    {
        $this->actingAs($user, 'api')->getJson('/api/courses/' . $course->id . '/program')
            ->assertForbidden();
    }
}
