<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\Event;
use Ulams\Compliance\Events\PathCompleted;
use Ulams\Compliance\Events\PathEnrolled;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\LearningPath;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;

class PathApiTest extends TestCase
{
    use CreatesUsers;

    /** @return array{0: Course, 1: Course, 2: Course} */
    private function courses(): array
    {
        return [
            Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Basics']),
            Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Advanced']),
            Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Exam']),
        ];
    }

    private function createPath(array $courses, $admin): array
    {
        [$basics, $advanced, $exam] = $courses;

        return $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/paths', [
            'title' => 'Onboarding',
            'steps' => [
                ['course_id' => $basics->getKey()],
                ['course_id' => $advanced->getKey(), 'prerequisites' => [$basics->getKey()]],
                ['course_id' => $exam->getKey(), 'prerequisites' => [$basics->getKey(), $advanced->getKey()]],
            ],
        ])->assertCreated()->json('data');
    }

    private function finish($user, Course $course): void
    {
        CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->update(['finished' => true]);
        event(new CourseFinished(CoreUser::find($user->getKey()), $course));
    }

    public function testPathsAreManagedByAdminsOnly(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student, 'api')->postJson('/api/admin/compliance/paths', ['title' => 'x'])->assertForbidden();
        $this->actingAs($student, 'api')->getJson('/api/admin/compliance/paths')->assertForbidden();
        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/compliance/paths', [])->assertStatus(422);
    }

    public function testStepsAreValidated(): void
    {
        [$a, $b] = $this->courses();
        $admin = $this->makeAdmin();
        $post = fn (array $steps) => $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/paths', ['title' => 'P', 'steps' => $steps]);

        $post([['course_id' => $a->getKey(), 'prerequisites' => [$a->getKey()]]])->assertStatus(422);
        $post([['course_id' => $a->getKey(), 'prerequisites' => [999]]])->assertStatus(422);
        $post([['course_id' => $a->getKey()], ['course_id' => $a->getKey()]])->assertStatus(422);
        $post([['course_id' => 999999]])->assertStatus(422);
        $post([
            ['course_id' => $a->getKey(), 'prerequisites' => [$b->getKey()]],
            ['course_id' => $b->getKey(), 'prerequisites' => [$a->getKey()]],
        ])->assertStatus(422)->assertJsonValidationErrors('steps');
        $this->assertSame(0, LearningPath::query()->count());
    }

    public function testPathCrud(): void
    {
        [$a, $b] = $this->courses();
        $admin = $this->makeAdmin();
        $path = $this->createPath([$a, $b, $this->courses()[2]], $admin);

        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/paths')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.steps.1.prerequisites', [$a->getKey()]);
        $this->actingAs($admin, 'api')->putJson('/api/admin/compliance/paths/' . $path['id'], ['title' => 'Renamed', 'steps' => [['course_id' => $b->getKey()]]])
            ->assertOk()->assertJsonPath('data.title', 'Renamed')->assertJsonCount(1, 'data.steps');
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/paths/' . $path['id'])->assertOk()->assertJsonPath('data.enrollments', 0);
        $this->actingAs($admin, 'api')->deleteJson('/api/admin/compliance/paths/' . $path['id'])->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/paths/' . $path['id'])->assertNotFound();
    }

    public function testEnrolmentAssignsOnlyTheUnlockedStepsAndUnlocksTheRestAsCoursesAreFinished(): void
    {
        Event::fake([PathEnrolled::class, PathCompleted::class]);
        [$basics, $advanced, $exam] = $courses = $this->courses();
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $path = $this->createPath($courses, $admin);
        $due = now()->addDays(30);

        $this->actingAs($admin, 'api')->postJson("/api/admin/compliance/paths/{$path['id']}/enrollments", ['user_ids' => [$student->getKey()], 'due_at' => $due->toIso8601String()])
            ->assertCreated();
        Event::assertDispatched(PathEnrolled::class, fn ($e) => $e->getUser()->is($student));

        $assigned = fn () => Assignment::query()->where('user_id', $student->getKey())->orderBy('id')->pluck('course_id')->all();
        $this->assertSame([$basics->getKey()], $assigned());

        $this->actingAs($student, 'api')->getJson('/api/compliance/my-paths')->assertOk()
            ->assertJsonPath('data.0.steps.0.status', 'available')
            ->assertJsonPath('data.0.steps.1.status', 'locked')
            ->assertJsonPath('data.0.steps.1.waiting_for', [$basics->getKey()]);

        $this->finish($student, $basics);
        $this->assertSame([$basics->getKey(), $advanced->getKey()], $assigned());
        $this->assertSame('completed', Assignment::query()->where('course_id', $basics->getKey())->first()->status);

        $this->finish($student, $advanced);
        $this->assertSame([$basics->getKey(), $advanced->getKey(), $exam->getKey()], $assigned());
        Event::assertNotDispatched(PathCompleted::class);

        $this->finish($student, $exam);
        Event::assertDispatched(PathCompleted::class, fn ($e) => $e->getUser()->is($student));
        $this->actingAs($student, 'api')->getJson('/api/compliance/my-paths')->assertJsonPath('data.0.steps.2.status', 'done');
        $this->assertNotNull($this->actingAs($student, 'api')->getJson('/api/compliance/my-paths')->json('data.0.completed_at'));
    }

    public function testEnrolmentIsIdempotentAndSkipsWhatIsAlreadyDone(): void
    {
        Event::fake([PathEnrolled::class]);
        [$basics, $advanced, $exam] = $courses = $this->courses();
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $student->courses()->attach($basics->getKey(), ['finished' => true]);
        $path = $this->createPath($courses, $admin);
        $url = "/api/admin/compliance/paths/{$path['id']}/enrollments";

        $this->actingAs($admin, 'api')->postJson($url, ['user_ids' => [$student->getKey()]])->assertCreated();
        $this->actingAs($admin, 'api')->postJson($url, ['user_ids' => [$student->getKey()]])->assertCreated();

        // the finished course is not assigned again (and its progress is untouched); the step it opens is
        $this->assertSame([$advanced->getKey()], Assignment::query()->where('user_id', $student->getKey())->pluck('course_id')->all());
        $this->assertTrue((bool) CourseUserPivot::query()->where('user_id', $student->getKey())->where('course_id', $basics->getKey())->first()->finished);
        Event::assertDispatchedTimes(PathEnrolled::class, 1);
    }

    public function testRemovingAccessDoesNotUnlockSteps(): void
    {
        [$basics, $advanced] = $courses = $this->courses();
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $path = $this->createPath($courses, $admin);
        $this->actingAs($admin, 'api')->postJson("/api/admin/compliance/paths/{$path['id']}/enrollments", ['user_ids' => [$student->getKey()]])->assertCreated();

        event(new CourseFinished(CoreUser::find($student->getKey()), $basics)); // fired on access removal too

        $this->assertSame([$basics->getKey()], Assignment::query()->where('user_id', $student->getKey())->pluck('course_id')->all());
    }
}
