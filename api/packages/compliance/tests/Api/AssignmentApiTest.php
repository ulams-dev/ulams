<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\Event;
use Ulams\Compliance\Events\TrainingAssigned;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\ManagerLink;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\TemplatesPdf\Models\FabricPDF;

class AssignmentApiTest extends TestCase
{
    use CreatesUsers;

    private function course(): Course
    {
        return Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
    }

    public function testAdminAssignsACourseWithDueDateAndAccess(): void
    {
        Event::fake([TrainingAssigned::class]);
        $course = $this->course();
        $student = $this->makeStudent();
        $due = now()->addDays(14)->startOfSecond();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/compliance/assignments', ['course_id' => $course->getKey(), 'user_ids' => [$student->getKey()], 'due_at' => $due->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'assigned')
            ->assertJsonPath('data.0.user.id', $student->getKey())
            ->assertJsonPath('data.0.course.id', $course->getKey());

        $pivot = CourseUserPivot::query()->where('user_id', $student->getKey())->where('course_id', $course->getKey())->firstOrFail();
        $this->assertEquals($due->timestamp, $pivot->deadline->timestamp);
        Event::assertDispatched(TrainingAssigned::class, fn (TrainingAssigned $e) => $e->getUser()->getKey() === $student->getKey());
    }

    public function testAssigningTwiceReturnsTheOpenAssignmentAndOnlyBringsTheDueDateForward(): void
    {
        Event::fake([TrainingAssigned::class]);
        $course = $this->course();
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        $post = fn (string $due) => $this->actingAs($admin, 'api')
            ->postJson('/api/admin/compliance/assignments', ['course_id' => $course->getKey(), 'user_ids' => [$student->getKey()], 'due_at' => $due])
            ->assertCreated();

        $first = $post(now()->addDays(10)->toIso8601String())->json('data.0.id');
        $second = $post(now()->addDays(20)->toIso8601String())->json('data.0.id');
        $third = $post(now()->addDays(5)->toIso8601String());

        $this->assertSame($first, $second);
        $this->assertSame($first, $third->json('data.0.id'));
        $this->assertSame(1, Assignment::query()->count());
        $this->assertEqualsWithDelta(now()->addDays(5)->timestamp, Assignment::query()->first()->due_at->timestamp, 5);
        Event::assertDispatchedTimes(TrainingAssigned::class, 1);
    }

    public function testAValidCertificateSatisfiesTheAssignmentAtOnce(): void
    {
        Event::fake([TrainingAssigned::class]);
        $course = $this->course();
        $student = $this->makeStudent();
        $certificate = FabricPDF::factory()->createOne([
            'user_id' => $student->getKey(), 'assignable_type' => Course::class, 'assignable_id' => $course->getKey(), 'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/compliance/assignments', ['course_id' => $course->getKey(), 'user_ids' => [$student->getKey()]])
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.certificate.id', $certificate->getKey());
        Event::assertNotDispatched(TrainingAssigned::class);
    }

    public function testAnExpiredCertificateDoesNotSatisfyAndTheCourseStartsOver(): void
    {
        Event::fake([TrainingAssigned::class]);
        $course = $this->course();
        $student = $this->makeStudent();
        $student->courses()->attach($course->getKey(), ['finished' => true]);
        FabricPDF::factory()->createOne([
            'user_id' => $student->getKey(), 'assignable_type' => Course::class, 'assignable_id' => $course->getKey(), 'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/compliance/assignments', ['course_id' => $course->getKey(), 'user_ids' => [$student->getKey()]])
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'assigned');

        $this->assertFalse((bool) CourseUserPivot::query()->where('user_id', $student->getKey())->first()->finished);
    }

    public function testAssignmentsNeedThePermissionAndValidInput(): void
    {
        $course = $this->course();
        $student = $this->makeStudent();
        $body = ['course_id' => $course->getKey(), 'user_ids' => [$student->getKey()]];

        $this->postJson('/api/admin/compliance/assignments', $body)->assertUnauthorized();
        $this->actingAs($student, 'api')->postJson('/api/admin/compliance/assignments', $body)->assertForbidden();
        $this->actingAs($student, 'api')->getJson('/api/admin/compliance/assignments')->assertForbidden();
        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/compliance/assignments', ['course_id' => 999999, 'user_ids' => []])->assertStatus(422);
        $this->assertSame(0, Assignment::query()->count());
    }

    public function testListFiltersByStatusAndCancelling(): void
    {
        $course = $this->course();
        $admin = $this->makeAdmin();
        [$late, $soon, $none] = [$this->makeStudent(), $this->makeStudent(), $this->makeStudent()];
        $make = fn ($user, $due) => Assignment::query()->create(['user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'status' => 'assigned', 'assigned_at' => now(), 'due_at' => $due]);
        $overdue = $make($late, now()->subDay());
        $make($soon, now()->addDay());
        $make($none, null);

        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/assignments?status=overdue')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $overdue->getKey())->assertJsonPath('data.0.status', 'overdue');
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/assignments?status=assigned')->assertJsonCount(2, 'data');
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/assignments?user_id=' . $soon->getKey())->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);

        $this->actingAs($admin, 'api')->deleteJson('/api/admin/compliance/assignments/' . $overdue->getKey())->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/assignments?status=overdue')->assertJsonCount(0, 'data');
        $this->actingAs($admin, 'api')->deleteJson('/api/admin/compliance/assignments/999999')->assertNotFound();
    }

    public function testLearnerSeesOnlyTheirOwnAssignments(): void
    {
        $course = $this->course();
        [$a, $b] = [$this->makeStudent(), $this->makeStudent()];
        foreach ([$a, $b] as $user) {
            Assignment::query()->create(['user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'assigned_at' => now()]);
        }

        $this->getJson('/api/compliance/my-assignments')->assertUnauthorized();
        $this->actingAs($a, 'api')->getJson('/api/compliance/my-assignments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.course.id', $course->getKey());
    }

    public function testManagerSeesOnlyDirectReports(): void
    {
        $course = $this->course();
        [$manager, $report, $stranger] = [$this->makeStudent(), $this->makeStudent(), $this->makeStudent()];
        ManagerLink::query()->create(['user_id' => $report->getKey(), 'manager_id' => $manager->getKey()]);
        foreach ([$report, $stranger] as $user) {
            Assignment::query()->create(['user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'assigned_at' => now()]);
        }

        $this->actingAs($manager, 'api')->getJson('/api/compliance/team/assignments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.id', $report->getKey());
        $this->actingAs($stranger, 'api')->getJson('/api/compliance/team/assignments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function testManagerLinksAreMaintainedByAdmins(): void
    {
        [$user, $manager] = [$this->makeStudent(), $this->makeStudent()];
        $admin = $this->makeAdmin();

        $this->actingAs($user, 'api')->putJson('/api/admin/compliance/managers/' . $user->getKey(), ['manager_id' => $manager->getKey()])->assertForbidden();
        $this->actingAs($admin, 'api')->putJson('/api/admin/compliance/managers/' . $user->getKey(), ['manager_id' => $user->getKey()])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/admin/compliance/managers/' . $user->getKey(), ['manager_id' => $manager->getKey()])
            ->assertOk()->assertJsonPath('data.manager_id', $manager->getKey());
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/managers')->assertOk()->assertJsonFragment(['user_id' => $user->getKey()]);
        $this->actingAs($admin, 'api')->putJson('/api/admin/compliance/managers/' . $user->getKey(), ['manager_id' => null])->assertOk()->assertJsonPath('data', null);
        $this->assertSame(0, ManagerLink::query()->count());
    }
}
