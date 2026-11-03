<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Ulams\Compliance\Mail\ScheduledReportMail;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\ReportSchedule;
use Ulams\Compliance\Services\ScheduledReportService;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;

class ScheduleApiTest extends TestCase
{
    use CreatesUsers;

    public function testSchedulesAreManagedWithTheReportPermission(): void
    {
        $admin = $this->makeAdmin();
        $recipient = $this->makeStudent();
        $body = ['name' => 'Weekly overdue', 'dataset' => 'assignments', 'frequency' => 'weekly', 'filters' => ['status' => 'overdue'], 'recipient_ids' => [$recipient->getKey()]];

        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/admin/compliance/schedules', $body)->assertForbidden();
        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', ['frequency' => 'hourly'] + $body)->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', ['dataset' => 'users'] + $body)->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', ['recipient_ids' => []] + $body)->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', ['recipient_ids' => [999999]] + $body)->assertStatus(422);

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', $body)->assertCreated()
            ->assertJsonPath('data.frequency', 'weekly')->assertJsonPath('data.filters.status', 'overdue')->json('data.id');
        $this->actingAs($admin, 'api')->putJson("/api/admin/compliance/schedules/{$id}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/schedules')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'api')->deleteJson("/api/admin/compliance/schedules/{$id}")->assertOk();
        $this->actingAs($admin, 'api')->deleteJson("/api/admin/compliance/schedules/{$id}")->assertNotFound();
    }

    public function testDueSchedulesAreMailedToTheirRecipientsOnlyAndRescheduled(): void
    {
        Mail::fake();
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $late = $this->makeStudent();
        Assignment::query()->create(['user_id' => $late->getKey(), 'course_id' => $course->getKey(), 'assigned_at' => now()->subDays(9), 'due_at' => now()->subDay()]);
        [$a, $b, $bystander] = [$this->makeStudent(['email' => 'a@acme.test']), $this->makeStudent(['email' => 'b@acme.test']), $this->makeStudent(['email' => 'c@acme.test'])];
        $due = ReportSchedule::query()->create(['name' => 'Overdue', 'dataset' => 'assignments', 'filters' => ['status' => 'overdue'], 'frequency' => 'daily', 'format' => 'csv', 'recipient_ids' => [$a->getKey(), $b->getKey()], 'next_run_at' => now()->subHour()]);
        $later = ReportSchedule::query()->create(['name' => 'Later', 'dataset' => 'assignments', 'frequency' => 'daily', 'format' => 'csv', 'recipient_ids' => [$bystander->getKey()], 'next_run_at' => now()->addDay()]);
        $off = ReportSchedule::query()->create(['name' => 'Off', 'dataset' => 'assignments', 'frequency' => 'daily', 'format' => 'csv', 'recipient_ids' => [$bystander->getKey()], 'active' => false, 'next_run_at' => now()->subHour()]);

        $sent = app(ScheduledReportService::class)->run(now());

        $this->assertSame(1, $sent);
        Mail::assertSent(ScheduledReportMail::class, 2);
        Mail::assertSent(ScheduledReportMail::class, fn (ScheduledReportMail $mail) => $mail->hasTo('a@acme.test') && $mail->rows === 1
            && str_starts_with($mail->contents, "\xEF\xBB\xBFassignment_id,") && $mail->mime === 'text/csv' && str_ends_with($mail->fileName, '.csv'));
        Mail::assertNotSent(ScheduledReportMail::class, fn (ScheduledReportMail $mail) => $mail->hasTo('c@acme.test'));

        $this->assertNotNull($due->fresh()->last_sent_at);
        $this->assertTrue($due->fresh()->next_run_at->isFuture());
        $this->assertNull($later->fresh()->last_sent_at);
        $this->assertNull($off->fresh()->last_sent_at);
        $this->assertSame(1, DB::table('compliance_exports')->whereNull('user_id')->count(), 'a scheduled export is recorded without a user');

        // not due any more: nothing is sent twice
        $this->assertSame(0, app(ScheduledReportService::class)->run(now()));
    }

    public function testAFailingScheduleStillMovesOn(): void
    {
        Mail::fake();
        $schedule = ReportSchedule::query()->create(['name' => 'Orphan', 'dataset' => 'assignments', 'frequency' => 'weekly', 'format' => 'csv', 'recipient_ids' => [999999], 'next_run_at' => now()->subHour()]);

        $this->assertSame(0, app(ScheduledReportService::class)->run(now()));

        Mail::assertNothingSent();
        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());
    }
}
