<?php

namespace Ulams\Compliance\Tests\Reporting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Reporting\AssignmentsDataset;
use Ulams\Compliance\Reporting\AuditDataset;
use Ulams\Compliance\Reporting\CertificatesDataset;
use Ulams\Compliance\Services\ComplianceReportService;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Support\ReportContext;
use Ulams\Reporting\Tests\Concerns\AssertsDatasetContract;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * The compliance datasets in the report registry (RB-1): same columns as the export, base queries that give the
 * same values as the export rows, and the registry as the single source of the column list.
 */
class ComplianceDatasetsTest extends TestCase
{
    use AssertsDatasetContract;
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
            'certificate_id' => (string) Str::uuid(), 'vars' => ['@VarCourseTitle' => 'Safety'], 'course_version' => 'v3', 'expires_at' => now()->addYear(),
        ]);
    }

    public function testThePackageRegistersItsThreeDatasets(): void
    {
        $registry = $this->app->make(DatasetRegistry::class);

        $this->assertSame(['ai_usage', 'assignments', 'certificates', 'audit'], $registry->keys());
        foreach (['assignments', 'certificates', 'audit'] as $key) {
            $this->assertSame('compliance_report', $registry->get($key)->permission());
        }
        $this->assertSame('user_id', $registry->get('assignments')->subjectColumn());
        $this->assertSame('user_id', $registry->get('certificates')->subjectColumn());
        $this->assertNull($registry->get('audit')->subjectColumn());
    }

    public function testEveryBaseQuerySelectsTheDeclaredColumns(): void
    {
        $this->assertDatasetContract(new AssignmentsDataset());
        $this->assertDatasetContract(new CertificatesDataset());
        $this->assertDatasetContract(new AuditDataset());
    }

    public function testTheExportColumnsComeFromTheRegistry(): void
    {
        $service = $this->app->make(ComplianceReportService::class);

        $this->assertSame((new AssignmentsDataset())->columnNames(), $service->columns('assignments'));
        $this->assertSame((new CertificatesDataset())->columnNames(), $service->columns('certificates'));
        $this->assertSame((new AuditDataset())->columnNames(), $service->columns('audit'));
        $this->assertTrue($service->available('audit'));
        $this->assertFalse($service->available('authoring'), 'a registered dataset of another package is not an export dataset');
        $this->assertSame(
            ['assignment_id', 'user_id', 'user_name', 'user_email', 'course_id', 'course_title', 'source', 'status', 'assigned_at', 'due_at', 'completed_at', 'days_overdue', 'escalation_level', 'certificate_id', 'certificate_status', 'certificate_issued_at', 'certificate_expires_at', 'course_version'],
            $service->columns('assignments'),
            'the columns of the export did not change',
        );
    }

    public function testAssignmentRowsMatchTheExportRows(): void
    {
        $done = $this->makeStudent(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $late = $this->makeStudent();
        $open = $this->makeStudent();
        $cert = $this->certificate($done, ['expires_at' => now()->subDay()]);
        Assignment::query()->create(['user_id' => $done->getKey(), 'course_id' => $this->course->getKey(), 'assigned_at' => now()->subDays(10), 'status' => 'completed', 'completed_at' => now()->subDays(2), 'certificate_id' => $cert->getKey(), 'due_at' => now()->addDays(5)]);
        Assignment::query()->create(['user_id' => $late->getKey(), 'course_id' => $this->course->getKey(), 'assigned_at' => now()->subDays(10), 'due_at' => now()->subDays(3)->subHour(), 'escalation_level' => 1]);
        Assignment::query()->create(['user_id' => $open->getKey(), 'course_id' => $this->course->getKey(), 'assigned_at' => now()->subDays(1), 'due_at' => now()->addDays(3), 'source' => 'rule']);
        Assignment::query()->create(['user_id' => $open->getKey(), 'course_id' => $this->course->getKey(), 'status' => 'cancelled', 'assigned_at' => now()->subDays(1)]);

        $service = $this->app->make(ComplianceReportService::class);
        $expected = $service->query('assignments', [])->get()->map(fn ($a) => $service->row('assignments', $a))->keyBy('assignment_id');
        $rows = (new AssignmentsDataset())->baseQuery(new ReportContext(null))->get()->keyBy('assignment_id');

        $this->assertCount(4, $rows);
        foreach ($expected as $id => $row) {
            $actual = (array) $rows[$id];
            foreach (['user_id', 'user_name', 'user_email', 'course_id', 'course_title', 'source', 'status', 'days_overdue', 'escalation_level', 'certificate_id', 'certificate_status', 'course_version'] as $column) {
                $this->assertEquals($row[$column], $actual[$column], "assignment {$id}: {$column}");
            }
            foreach (['assigned_at', 'due_at', 'completed_at', 'certificate_issued_at', 'certificate_expires_at'] as $column) {
                $this->assertSame(
                    $row[$column] === null ? null : \Illuminate\Support\Carbon::parse($row[$column])->utc()->format('Y-m-d H:i'),
                    $actual[$column] === null ? null : \Illuminate\Support\Carbon::parse($actual[$column], config('app.timezone'))->utc()->format('Y-m-d H:i'),
                    "assignment {$id}: {$column}",
                );
            }
        }
        $byStatus = $rows->groupBy('status')->map->count()->all();
        $this->assertSame(['completed' => 1, 'overdue' => 1, 'assigned' => 1, 'cancelled' => 1], $byStatus);
        $this->assertSame('expired', collect($rows)->firstWhere('status', 'completed')->certificate_status);
        $this->assertSame(3, (int) collect($rows)->firstWhere('status', 'overdue')->days_overdue);
    }

    public function testCertificateRowsMatchTheExportRows(): void
    {
        $this->certificate($this->makeStudent());
        $this->certificate($this->makeStudent(), ['expires_at' => now()->subDay()]);
        $this->certificate($this->makeStudent(), ['revoked_at' => now(), 'revoked_reason' => 'Policy superseded']);
        $old = $this->certificate($this->makeStudent());
        $old->forceFill(['superseded_by_id' => $old->getKey()])->save();
        // not a course certificate and not an issued one: outside the dataset, as in the export
        FabricPDF::factory()->createOne(['user_id' => $this->makeStudent()->getKey(), 'assignable_type' => 'other', 'assignable_id' => 1, 'certificate_id' => (string) Str::uuid()]);
        FabricPDF::factory()->createOne(['user_id' => $this->makeStudent()->getKey(), 'assignable_type' => (new Course())->getMorphClass(), 'assignable_id' => $this->course->getKey(), 'certificate_id' => null]);

        $service = $this->app->make(ComplianceReportService::class);
        $expected = $service->query('certificates', [])->get()->map(fn ($c) => $service->row('certificates', $c))->keyBy('certificate_id');
        $rows = (new CertificatesDataset())->baseQuery(new ReportContext(null))->get()->keyBy('certificate_id');

        $this->assertSame($expected->keys()->sort()->values()->all(), $rows->keys()->sort()->values()->all());
        $this->assertCount(4, $rows);
        foreach ($expected as $id => $row) {
            $actual = (array) $rows[$id];
            foreach (['user_id', 'user_name', 'user_email', 'course_id', 'course_title', 'status', 'revoked_reason', 'course_version'] as $column) {
                $this->assertEquals($row[$column], $actual[$column], "certificate {$id}: {$column}");
            }
            $this->assertSame($row['superseded'], (bool) $actual['superseded'], "certificate {$id}: superseded");
        }
        $this->assertEqualsCanonicalizing(['valid', 'expired', 'revoked', 'valid'], $rows->pluck('status')->all());
    }

    public function testADeletedLearnerIsNotNamed(): void
    {
        $user = $this->makeStudent(['first_name' => 'Gone', 'last_name' => 'Learner']);
        Assignment::query()->create(['user_id' => $user->getKey(), 'course_id' => $this->course->getKey(), 'assigned_at' => now()]);
        $this->certificate($user);
        $user->delete();

        $assignment = (new AssignmentsDataset())->baseQuery(new ReportContext(null))->first();
        $certificate = (new CertificatesDataset())->baseQuery(new ReportContext(null))->first();

        foreach ([$assignment, $certificate] as $row) {
            $this->assertNull($row->user_name);
            $this->assertNull($row->user_email);
            $this->assertSame($user->getKey(), (int) $row->user_id, 'the id stays so the row can still be counted');
        }
    }

    public function testAuditRowsLeaveOutIpUserAgentAndPayload(): void
    {
        DB::table('living_course_audit')->insert([
            'actor_type' => 'user', 'actor_id' => 1, 'action' => 'revision.applied', 'course_id' => $this->course->getKey(), 'version_from' => 2, 'version_to' => 3,
            'ip' => '203.0.113.9', 'user_agent' => 'curl', 'data' => '{"secret":"x"}', 'created_at' => now(), 'prev_hash' => str_repeat('0', 64), 'hash' => str_repeat('a', 64),
        ]);

        $row = (array) (new AuditDataset())->baseQuery(new ReportContext(null))->first();

        $this->assertSame('revision.applied', $row['action']);
        $this->assertSame([], array_intersect(['ip', 'user_agent', 'data', 'session_id', 'on_behalf_of'], array_keys($row)));
    }
}
