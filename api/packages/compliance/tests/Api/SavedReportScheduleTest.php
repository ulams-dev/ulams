<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Compliance\Jobs\SendSavedReportToRecipient;
use Ulams\Compliance\Mail\ScheduledReportMail;
use Ulams\Compliance\Mail\ScheduledReportSkippedMail;
use Ulams\Compliance\Models\ReportSchedule;
use Ulams\Compliance\Services\ScheduledReportService;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Reporting\Database\Seeders\ReportingPermissionSeeder;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Tests\Support\ScopedCallsDataset;

/** Schedules of a saved report (RB-5): validation, one run per recipient with their own scope, skipped recipients, XLSX. */
class SavedReportScheduleTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReportingPermissionSeeder::class);
        $this->app->make(DatasetRegistry::class)->register(new ScopedCallsDataset());
    }

    private function aCall(int $user, string $task): void
    {
        DB::table('ai_calls')->insert(['id' => (string) Str::ulid(), 'task' => $task, 'driver' => 'fake', 'model_requested' => 'm', 'status' => 'ok', 'user_id' => $user, 'created_at' => now()]);
    }

    private function savedReport($owner, ?array $definition = null): SavedReport
    {
        return SavedReport::query()->create([
            'name' => 'Calls per person', 'owner_user_id' => $owner->getKey(), 'visibility' => 'private',
            'definition' => $definition ?? ['dataset' => 'my_calls', 'columns' => ['task', 'user_id'], 'sort' => [['column' => 'task']]],
        ]);
    }

    /** A user who may read reports through a role of this tenant (no manage: they see their own rows). */
    private function partner(string $email)
    {
        $role = Role::findOrCreate('partner', 'api');
        $role->givePermissionTo(Permission::findOrCreate('reporting_read', 'api'));
        $user = config('auth.providers.users.model')::factory()->create(['email' => $email]);
        $user->assignRole('partner');

        return $user;
    }

    private function due(SavedReport $report, array $recipients, string $format = 'csv', ?int $createdBy = null): ReportSchedule
    {
        return ReportSchedule::query()->create([
            'name' => 'Weekly calls', 'dataset' => 'my_calls', 'saved_report_id' => $report->id, 'frequency' => 'weekly', 'format' => $format,
            'recipient_ids' => array_map(fn ($u) => $u->getKey(), $recipients), 'created_by' => $createdBy, 'next_run_at' => now()->subHour(),
        ]);
    }

    public function testASavedReportIsScheduledThroughTheSameEndpoint(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->savedReport($admin);
        $recipient = $this->makeStudent();
        $body = ['name' => 'Weekly calls', 'saved_report_id' => $report->id, 'frequency' => 'weekly', 'format' => 'xlsx', 'recipient_ids' => [$recipient->getKey()]];

        $data = $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', $body)->assertCreated()->json('data');

        $this->assertSame([$report->id, 'xlsx', 'weekly', []], [$data['saved_report_id'], $data['format'], $data['frequency'], $data['skipped_recipient_ids']]);
        $this->assertSame('my_calls', ReportSchedule::query()->find($data['id'])->dataset);
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/schedules')->assertJsonPath('data.0.saved_report_id', $report->id);
    }

    public function testTheDefinitionOfTheScheduleMustBeOneOrTheOther(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->savedReport($admin);
        $recipient = $this->makeStudent()->getKey();
        $post = fn (array $extra) => $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', $extra + ['name' => 'x', 'frequency' => 'daily', 'recipient_ids' => [$recipient]]);

        $post([])->assertStatus(422)->assertJsonValidationErrors(['dataset']);
        $post(['dataset' => 'assignments', 'saved_report_id' => $report->id])->assertStatus(422)->assertJsonValidationErrors(['dataset']);
        $post(['saved_report_id' => str_repeat('0', 26)])->assertStatus(422)->assertJsonValidationErrors(['saved_report_id']);
        $post(['dataset' => 'assignments', 'format' => 'xlsx'])->assertStatus(422)->assertJsonValidationErrors(['format']);
        $post(['dataset' => 'assignments', 'format' => 'pdf'])->assertStatus(422);
        $post(['dataset' => 'assignments', 'format' => 'jsonl'])->assertCreated();
    }

    public function testAReportOnlyItsOwnerCanSeeCannotBeScheduledByAnotherAdministratorWithoutManage(): void
    {
        $owner = $this->makeAdmin();
        $report = $this->savedReport($owner);
        $builder = $this->makeInstructor();
        $builder->givePermissionTo(['compliance_report', 'reporting_create']);

        $this->actingAs($builder, 'api')->postJson('/api/admin/compliance/schedules', ['name' => 'x', 'saved_report_id' => $report->id, 'frequency' => 'daily', 'recipient_ids' => [$owner->getKey()]])
            ->assertStatus(422)->assertJsonValidationErrors(['saved_report_id']);
    }

    public function testAReportThatNeedsRepairCannotBeScheduled(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->savedReport($admin, ['dataset' => 'my_calls', 'columns' => ['gone']]);

        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', ['name' => 'x', 'saved_report_id' => $report->id, 'frequency' => 'daily', 'recipient_ids' => [$admin->getKey()]])
            ->assertStatus(422)->assertJsonValidationErrors(['saved_report_id']);
    }

    public function testChangingAScheduleBetweenADatasetAndASavedReport(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->savedReport($admin);
        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/schedules', ['name' => 'x', 'saved_report_id' => $report->id, 'frequency' => 'daily', 'recipient_ids' => [$admin->getKey()]])->json('data.id');

        $this->actingAs($admin, 'api')->putJson("/api/admin/compliance/schedules/{$id}", ['format' => 'jsonl'])->assertOk()->assertJsonPath('data.format', 'jsonl')->assertJsonPath('data.saved_report_id', $report->id);
        $this->actingAs($admin, 'api')->putJson("/api/admin/compliance/schedules/{$id}", ['dataset' => 'assignments', 'format' => 'csv'])->assertOk()->assertJsonPath('data.saved_report_id', null)->assertJsonPath('data.dataset', 'assignments');
        $this->actingAs($admin, 'api')->putJson("/api/admin/compliance/schedules/{$id}", ['format' => 'xlsx'])->assertStatus(422);
    }

    public function testTheReportRunsOncePerRecipientWithTheirOwnScope(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $ada = $this->partner('ada@acme.test');
        $bob = $this->partner('bob@acme.test');
        $this->aCall($ada->getKey(), 'ada-1');
        $this->aCall($ada->getKey(), 'ada-2');
        $this->aCall($bob->getKey(), 'bob-1');
        $manager = $this->makeAdmin(['email' => 'boss@acme.test']);
        $schedule = $this->due($this->savedReport($admin), [$ada, $bob, $manager], 'csv', $admin->getKey());

        $this->assertSame(1, app(ScheduledReportService::class)->run(now()));

        Mail::assertSent(ScheduledReportMail::class, 3);
        $csv = fn (string $to) => collect(Mail::sent(ScheduledReportMail::class))->first(fn ($m) => $m->hasTo($to));
        $this->assertStringContainsString('ada-1', $csv('ada@acme.test')->contents);
        $this->assertStringNotContainsString('bob-1', $csv('ada@acme.test')->contents);
        $this->assertSame(2, $csv('ada@acme.test')->rows);
        $this->assertSame(1, $csv('bob@acme.test')->rows);
        $this->assertSame(3, $csv('boss@acme.test')->rows, 'a manager sees every row');
        $this->assertStringContainsString('text/csv', $csv('ada@acme.test')->mime);
        $this->assertStringEndsWith('.csv', $csv('ada@acme.test')->fileName);
        Mail::assertNotSent(ScheduledReportSkippedMail::class);
        $this->assertNotNull($schedule->fresh()->last_sent_at);
        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());

        $exports = DB::table('compliance_exports')->where('channel', 'schedule')->orderBy('user_id')->get();
        $this->assertCount(3, $exports, 'each recipient run is recorded, as that recipient');
        $this->assertEqualsCanonicalizing([$ada->getKey(), $bob->getKey(), $manager->getKey()], $exports->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$schedule->saved_report_id], $exports->pluck('saved_report_id')->unique()->values()->all());

        $this->assertSame(0, app(ScheduledReportService::class)->run(now()), 'not due any more: nothing is sent twice');
    }

    public function testOneQueuedJobPerRecipientWhoCanRunTheReport(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();
        [$ada, $bob] = [$this->partner('ada@acme.test'), $this->partner('bob@acme.test')];
        $this->due($this->savedReport($admin), [$ada, $bob], 'jsonl', $admin->getKey());

        app(ScheduledReportService::class)->run(now());

        Queue::assertPushed(SendSavedReportToRecipient::class, 2);
        Queue::assertPushed(SendSavedReportToRecipient::class, fn ($job) => $job->userId === $ada->getKey());
    }

    public function testARecipientWhoCannotRunTheReportIsSkippedAndTheOwnerIsTold(): void
    {
        Mail::fake();
        $owner = $this->makeAdmin(['email' => 'owner@acme.test']);
        $ada = $this->partner('ada@acme.test');
        $student = $this->makeStudent(['email' => 'student@acme.test', 'first_name' => 'Stu', 'last_name' => 'Dent']);
        $noAddress = $this->makeStudent(['email' => null]);
        $this->aCall($ada->getKey(), 'ada-1');
        $schedule = $this->due($this->savedReport($owner), [$ada, $student, $noAddress], 'csv', $owner->getKey());
        $schedule->forceFill(['recipient_ids' => [...$schedule->recipient_ids, 999999]])->save();

        app(ScheduledReportService::class)->run(now());

        Mail::assertSent(ScheduledReportMail::class, 1);
        Mail::assertSent(ScheduledReportMail::class, fn ($m) => $m->hasTo('ada@acme.test'));
        Mail::assertNotSent(ScheduledReportMail::class, fn ($m) => $m->hasTo('student@acme.test'));
        Mail::assertSent(ScheduledReportSkippedMail::class, function (ScheduledReportSkippedMail $mail) {
            $names = array_column($mail->skipped, 'name');

            return $mail->hasTo('owner@acme.test') && $mail->reportName === 'Weekly calls' && in_array('Stu Dent', $names, true) && in_array('#999999', $names, true) && count($mail->skipped) === 3;
        });
        $reasons = collect(collect(Mail::sent(ScheduledReportSkippedMail::class))->first()->skipped)->pluck('reason', 'name');
        $this->assertSame('no permission for this report', (string) $reasons['Stu Dent']);
        $this->assertSame('the user no longer exists', (string) $reasons['#999999']);
        $this->assertEqualsCanonicalizing([$student->getKey(), $noAddress->getKey(), 999999], $schedule->fresh()->skipped_recipient_ids);
        $rendered = (new ScheduledReportSkippedMail('Weekly calls', [['name' => 'Stu Dent', 'reason' => 'no permission for this report']]))->render();
        $this->assertStringContainsString('Stu Dent: no permission for this report', $rendered);
    }

    public function testADeletedSavedReportSkipsEveryoneAndSendsNothing(): void
    {
        Mail::fake();
        $owner = $this->makeAdmin(['email' => 'owner@acme.test']);
        $report = $this->savedReport($owner);
        $schedule = $this->due($report, [$owner], 'csv', $owner->getKey());
        $report->delete();

        $this->assertSame(0, app(ScheduledReportService::class)->run(now()));

        Mail::assertNotSent(ScheduledReportMail::class);
        Mail::assertSent(ScheduledReportSkippedMail::class, fn ($m) => $m->skipped[0]['reason'] === 'the saved report was deleted');
        $this->assertTrue($schedule->fresh()->next_run_at->isFuture(), 'the schedule still moves on');
    }

    public function testARecipientWhoLostAccessBetweenTheRunAndTheJobGetsNothing(): void
    {
        Mail::fake();
        $owner = $this->makeAdmin();
        $ada = $this->partner('ada@acme.test');
        $this->aCall($ada->getKey(), 'ada-1');
        $schedule = $this->due($this->savedReport($owner), [$ada], 'csv', $owner->getKey());
        $ada->removeRole('partner');

        (new SendSavedReportToRecipient($schedule->getKey(), $ada->getKey()))->handle(app(\Ulams\Reporting\Export\ReportExporter::class));

        Mail::assertNothingSent();
    }

    public function testAnXlsxScheduleMailsAWorkbook(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin(['email' => 'boss@acme.test']);
        $this->aCall($admin->getKey(), 'one');
        $this->due($this->savedReport($admin), [$admin], 'xlsx', $admin->getKey());

        app(ScheduledReportService::class)->run(now());

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) {
            $path = tempnam(sys_get_temp_dir(), 'sched-xlsx-');
            file_put_contents($path, $mail->contents);
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            @unlink($path);

            return str_starts_with($mail->contents, 'PK') && str_ends_with($mail->fileName, '.xlsx') && str_contains($mail->mime, 'spreadsheetml.sheet')
                && $sheet->getCell('A2')->getFormattedValue() === 'one' && $mail->rows === 1;
        });
    }

    public function testDatasetSchedulesStillSendTheCompliancePath(): void
    {
        Mail::fake();
        $a = $this->makeStudent(['email' => 'a@acme.test']);
        ReportSchedule::query()->create(['name' => 'Overdue', 'dataset' => 'assignments', 'frequency' => 'daily', 'format' => 'csv', 'recipient_ids' => [$a->getKey()], 'next_run_at' => now()->subHour()]);

        $this->assertSame(1, app(ScheduledReportService::class)->run(now()));

        Mail::assertSent(ScheduledReportMail::class, fn ($m) => $m->hasTo('a@acme.test') && str_starts_with($m->fileName, 'compliance-assignments-'));
        $this->assertSame(0, DB::table('compliance_exports')->where('channel', 'schedule')->count(), 'dataset schedules keep their own log entry');
    }
}
