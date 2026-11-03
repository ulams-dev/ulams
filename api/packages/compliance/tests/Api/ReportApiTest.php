<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Services\CompletionService;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

class ReportApiTest extends TestCase
{
    use CreatesUsers;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Safety']);
    }

    private function certificate($user, array $extra = []): FabricPDF
    {
        return FabricPDF::factory()->createOne($extra + [
            'user_id' => $user->getKey(), 'assignable_type' => Course::class, 'assignable_id' => $this->course->getKey(),
            'certificate_id' => (string) \Illuminate\Support\Str::uuid(), 'vars' => ['@VarCourseTitle' => 'Safety'], 'course_version' => 'v3',
            'expires_at' => now()->addYear(),
        ]);
    }

    private function assignment($user, array $extra = []): Assignment
    {
        return Assignment::query()->create($extra + ['user_id' => $user->getKey(), 'course_id' => $this->course->getKey(), 'assigned_at' => now()->subDays(10)]);
    }

    public function testReportNeedsThePermission(): void
    {
        $student = $this->makeStudent();

        $this->getJson('/api/admin/compliance/report?dataset=assignments')->assertUnauthorized();
        $this->actingAs($student, 'api')->getJson('/api/admin/compliance/report?dataset=assignments')->assertForbidden();
        $this->actingAs($student, 'api')->get('/api/admin/compliance/report/export?dataset=assignments')->assertForbidden();
        $this->assertSame(0, DB::table('compliance_exports')->count());
    }

    public function testUnknownDatasetIsRejected(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/compliance/report?dataset=users')->assertStatus(422);
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/compliance/report')->assertStatus(422);
    }

    public function testAssignmentsReportShowsWhoCompletedWhatWhenAndWithWhichCertificate(): void
    {
        $done = $this->makeStudent(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $late = $this->makeStudent();
        $cert = $this->certificate($done);
        $this->assignment($done, ['status' => 'completed', 'completed_at' => now()->subDays(2), 'certificate_id' => $cert->getKey(), 'due_at' => now()->addDays(5)]);
        $this->assignment($late, ['due_at' => now()->subDays(3)]);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/report?dataset=assignments')->assertOk()->assertJsonPath('meta.total', 2);
        $rows = collect($response->json('data.rows'))->keyBy('user_id');

        $this->assertSame('completed', $rows[$done->getKey()]['status']);
        $this->assertSame('Ada Lovelace', $rows[$done->getKey()]['user_name']);
        $this->assertSame($cert->certificate_id, $rows[$done->getKey()]['certificate_id']);
        $this->assertSame('v3', $rows[$done->getKey()]['course_version']);
        $this->assertSame('valid', $rows[$done->getKey()]['certificate_status']);
        $this->assertSame('overdue', $rows[$late->getKey()]['status']);
        $this->assertSame(3, $rows[$late->getKey()]['days_overdue']);
        $this->assertSame('course_version', $response->json('data.columns')[17]);

        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/report?dataset=assignments&status=overdue')->assertJsonPath('meta.total', 1);
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/report?dataset=assignments&user_id=' . $done->getKey())->assertJsonPath('meta.total', 1);
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/report?dataset=assignments&from=' . now()->addDay()->toDateString())->assertJsonPath('meta.total', 0);
    }

    public function testCertificatesReportCoversStatusesAndRevocation(): void
    {
        [$a, $b, $c] = [$this->makeStudent(), $this->makeStudent(), $this->makeStudent()];
        $this->certificate($a);
        $this->certificate($b, ['expires_at' => now()->subDay()]);
        $this->certificate($c, ['revoked_at' => now(), 'revoked_reason' => 'Policy superseded']);
        $admin = $this->makeAdmin();

        $all = $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/report?dataset=certificates')->assertJsonPath('meta.total', 3);
        $this->assertEqualsCanonicalizing(['valid', 'expired', 'revoked'], collect($all->json('data.rows'))->pluck('status')->all());
        $this->assertSame('Policy superseded', collect($all->json('data.rows'))->firstWhere('status', 'revoked')['revoked_reason']);
        foreach (['valid', 'expired', 'revoked'] as $status) {
            $this->actingAs($admin, 'api')->getJson("/api/admin/compliance/report?dataset=certificates&status={$status}")->assertJsonPath('meta.total', 1);
        }
    }

    public function testCsvExportIsStreamedAuditedAndSafeInSpreadsheets(): void
    {
        $user = $this->makeStudent(['first_name' => '=HYPERLINK("http://evil")', 'last_name' => 'Smith']);
        $this->certificate($user);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')->get('/api/admin/compliance/report/export?dataset=certificates&course_id=' . $this->course->getKey());
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('compliance-certificates-', $response->headers->get('Content-Disposition'));
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'certificate_id,user_id', $csv);
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString("\"'=HYPERLINK", $lines[1]);
        $this->assertStringContainsString(',v3', $lines[1]);

        $log = DB::table('compliance_exports')->first();
        $this->assertSame($admin->getKey(), $log->user_id);
        $this->assertSame('certificates', $log->dataset);
        $this->assertSame(1, $log->row_count);
    }

    public function testJsonLinesExport(): void
    {
        $this->certificate($this->makeStudent());
        $this->certificate($this->makeStudent());

        $response = $this->actingAs($this->makeAdmin(), 'api')->get('/api/admin/compliance/report/export?dataset=certificates&format=jsonl');
        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));

        $this->assertCount(2, $lines);
        $this->assertSame('v3', json_decode($lines[0], true)['course_version']);
        $this->assertSame(2, DB::table('compliance_exports')->first()->row_count);
    }

    public function testAuditDatasetListsTheHashChainedEntries(): void
    {
        DB::table('living_course_audit')->insert([
            'actor_type' => 'user', 'actor_id' => 1, 'action' => 'revision.applied', 'course_id' => $this->course->getKey(),
            'version_from' => 2, 'version_to' => 3, 'created_at' => now(), 'prev_hash' => str_repeat('0', 64), 'hash' => str_repeat('a', 64),
        ]);

        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/compliance/report?dataset=audit&course_id=' . $this->course->getKey())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.rows.0.action', 'revision.applied')
            ->assertJsonPath('data.rows.0.version_to', 3)
            ->assertJsonPath('data.rows.0.hash', str_repeat('a', 64));
    }

    public function testACertificateRecordsTheLivingCourseVersionWhenThereIsOne(): void
    {
        DB::table('living_course_audit')->insert([
            'actor_type' => 'system', 'action' => 'revision.applied', 'course_id' => $this->course->getKey(),
            'version_from' => 4, 'version_to' => 5, 'created_at' => now(), 'prev_hash' => str_repeat('0', 64), 'hash' => str_repeat('b', 64),
        ]);
        $certificate = $this->certificate($this->makeStudent(), ['course_version' => '2026-01-01T00:00:00Z']);

        app(CompletionService::class)->certificateIssued($certificate);

        $this->assertSame('v5', $certificate->fresh()->course_version);
    }
}
