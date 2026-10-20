<?php

namespace Ulams\Compliance\Tests\Api;

use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

class DashboardApiTest extends TestCase
{
    use CreatesUsers;

    private function certificate($user, Course $course, array $extra = []): FabricPDF
    {
        return FabricPDF::factory()->createOne($extra + [
            'user_id' => $user->getKey(), 'assignable_type' => Course::class, 'assignable_id' => $course->getKey(),
            'certificate_id' => (string) \Illuminate\Support\Str::uuid(), 'expires_at' => now()->addYear(),
        ]);
    }

    public function testDashboardNeedsThePermission(): void
    {
        $this->getJson('/api/admin/compliance/dashboard')->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->getJson('/api/admin/compliance/dashboard')->assertForbidden();
    }

    public function testEmptyDashboardHasNoRates(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/compliance/dashboard')
            ->assertOk()
            ->assertJsonPath('data.assignments.open', 0)
            ->assertJsonPath('data.assignments.completion_rate', null)
            ->assertJsonPath('data.assignments.avg_days_to_complete', null)
            ->assertJsonPath('data.courses', []);
    }

    public function testDashboardCountsAssignmentsAndCertificates(): void
    {
        $safety = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Safety']);
        $gdpr = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'GDPR']);
        $users = [$this->makeStudent(), $this->makeStudent(), $this->makeStudent(), $this->makeStudent()];
        $make = fn ($user, Course $course, array $extra) => Assignment::query()->create($extra + ['user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'assigned_at' => now()->subDays(10)]);
        $make($users[0], $safety, ['status' => 'completed', 'assigned_at' => now()->subDays(10), 'completed_at' => now()->subDays(4)]); // 6 days
        $make($users[1], $safety, ['due_at' => now()->subDay()]); // overdue
        $make($users[2], $safety, ['due_at' => now()->addDays(5)]); // open
        $make($users[3], $gdpr, ['status' => 'completed', 'assigned_at' => now()->subDays(20), 'completed_at' => now()->subDays(10)]); // 10 days
        $make($users[2], $gdpr, ['status' => 'cancelled']);
        $this->certificate($users[0], $safety);
        $this->certificate($users[1], $safety, ['expires_at' => now()->addDays(20)]);
        $this->certificate($users[2], $safety, ['expires_at' => now()->subDay()]);
        $this->certificate($users[3], $gdpr, ['revoked_at' => now()]);
        $this->certificate($users[2], $gdpr, ['superseded_by_id' => 1]); // replaced: not counted

        $data = $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/compliance/dashboard')->assertOk()->json('data');

        $this->assertEquals(['open' => 2, 'overdue' => 1, 'completed' => 2, 'cancelled' => 1, 'completed_last_30_days' => 2, 'completion_rate' => 50.0, 'avg_days_to_complete' => 8.0], $data['assignments']);
        $this->assertSame(['valid' => 2, 'expiring_30_days' => 1, 'expiring_90_days' => 1, 'expired' => 1, 'revoked' => 1], $data['certificates']);
        $this->assertSame(['Safety', 'GDPR'], array_column($data['courses'], 'title'), 'most overdue first');
        $this->assertEquals(['assigned' => 3, 'completed' => 1, 'open' => 2, 'overdue' => 1, 'completion_rate' => 33.3, 'avg_days_to_complete' => 6.0], array_intersect_key($data['courses'][0], array_flip(['assigned', 'completed', 'open', 'overdue', 'completion_rate', 'avg_days_to_complete'])));

        $one = $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/compliance/dashboard?course_id=' . $gdpr->getKey())->json('data');
        $this->assertSame(1, $one['assignments']['completed']);
        $this->assertCount(1, $one['courses']);
    }
}
