<?php

namespace Ulams\Compliance\Tests\Reporting;

use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Reporting\Database\Seeders\ReportingPermissionSeeder;

/** The report builder over the real compliance datasets (RB-2): permissions, grouping by course and month, withheld groups. */
class ReportQueryTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReportingPermissionSeeder::class);
    }

    private function course(string $title): Course
    {
        return Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => $title]);
    }

    /** @return list<int> the learners assigned */
    private function assign(Course $course, int $learners, string $assignedAt, string $status = 'assigned'): array
    {
        $ids = [];
        for ($i = 0; $i < $learners; $i++) {
            $user = $this->makeStudent();
            Assignment::query()->create(['user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'assigned_at' => $assignedAt, 'status' => $status, 'due_at' => now()->addMonth()]);
            $ids[] = $user->getKey();
        }

        return $ids;
    }

    private function ask(array $definition, ?object $as = null)
    {
        return $this->actingAs($as ?? $this->makeAdmin(), 'api')->postJson('/api/admin/reporting/query', $definition);
    }

    private function byCourseAndMonth(): array
    {
        return [
            'dataset' => 'assignments',
            'groupBy' => ['course_title', ['column' => 'assigned_at', 'bucket' => 'month']],
            'aggregates' => [['fn' => 'count_distinct', 'column' => 'user_id', 'as' => 'learners']],
            'sort' => [['column' => 'course_title'], ['column' => 'assigned_at_month']],
        ];
    }

    public function testAssignmentsGroupedByCourseAndMonthWithAGroupOfFourWithheld(): void
    {
        $safety = $this->course('Safety');
        $privacy = $this->course('Privacy');
        $fire = $this->course('Fire');
        $this->assign($safety, 12, '2026-10-05 09:00:00');
        $this->assign($privacy, 4, '2026-10-06 09:00:00');
        $this->assign($fire, 15, '2026-10-07 09:00:00');

        $response = $this->ask($this->byCourseAndMonth())->assertOk();

        $rows = $response->json('data.rows');
        // Privacy has four learners; with Privacy alone withheld, Safety = total - Fire would be readable, so Safety goes too
        $this->assertSame(['Fire', '2026-10-01', 15], [$rows[0]['course_title'], $rows[0]['assigned_at_month'], $rows[0]['learners']]);
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[1]['_withheld']);
        $this->assertSame('withheld (fewer than 10 learners)', $rows[1]['course_title']);
        $this->assertNull($rows[1]['learners']);
        $this->assertSame(2, $response->json('meta.withheld_groups'));
        $this->assertStringNotContainsString('Privacy', json_encode($rows));
        $this->assertStringNotContainsString('Safety', json_encode($rows));
        $this->assertSame([['name' => 'course_title', 'type' => 'string', 'label' => 'Course'], ['name' => 'assigned_at_month', 'type' => 'date', 'label' => 'Assigned (month)'], ['name' => 'learners', 'type' => 'int', 'label' => 'learners']], $response->json('data.columns'));
    }

    public function testTheSameLearnersInTwoMonthsAreCountedOnceTogether(): void
    {
        $course = $this->course('Safety');
        $other = $this->course('Fire');
        $ids = $this->assign($other, 20, '2026-10-01 09:00:00');
        foreach (['2026-08-10 09:00:00', '2026-09-10 09:00:00'] as $month) {
            foreach (array_slice($ids, 0, 4) as $id) {
                Assignment::query()->create(['user_id' => $id, 'course_id' => $course->getKey(), 'assigned_at' => $month]);
            }
        }

        $rows = $this->ask($this->byCourseAndMonth())->assertOk()->json('data.rows');

        // Safety in August and in September are two small groups of the same four learners: four together, still small
        $this->assertSame(1, count($rows));
        $this->assertTrue($rows[0]['_withheld']);
    }

    public function testStatusFiltersCountersAndTheDateRangeApply(): void
    {
        $safety = $this->course('Safety');
        $this->assign($safety, 11, now()->subDays(10)->toDateTimeString(), 'completed');
        $this->assign($safety, 12, now()->subDays(200)->toDateTimeString());

        $recent = $this->ask([
            'dataset' => 'assignments', 'groupBy' => ['status'], 'aggregates' => [['fn' => 'count', 'as' => 'assignments']],
            'dateRange' => ['column' => 'assigned_at', 'preset' => 'last_90_days'],
        ])->assertOk()->json('data.rows');
        $this->assertSame([['status' => 'completed', 'assignments' => 11]], $recent);

        $all = $this->ask(['dataset' => 'assignments', 'groupBy' => ['status'], 'aggregates' => [['fn' => 'count', 'as' => 'assignments']], 'filters' => [['column' => 'status', 'op' => 'in', 'value' => ['completed', 'assigned']]], 'sort' => [['column' => 'assignments', 'dir' => 'desc']]])->assertOk()->json('data.rows');
        $this->assertSame([12, 11], array_column($all, 'assignments'));
    }

    public function testTheViewerNeedsThePermissionOfTheDataset(): void
    {
        $safety = $this->course('Safety');
        $this->assign($safety, 1, '2026-10-05 09:00:00');
        $definition = ['dataset' => 'assignments', 'columns' => ['course_title', 'status']];

        $this->ask($definition, $this->makeInstructor())->assertForbidden();
        $this->ask(['dataset' => 'ai_usage', 'columns' => ['task']], $this->makeInstructor())->assertForbidden();
        $this->ask($definition, $this->makeAdmin())->assertOk()->assertJsonPath('meta.total_rows', 1);

        $tutor = $this->makeInstructor();
        $tutor->givePermissionTo('compliance_report');
        $this->ask($definition, $tutor)->assertOk();
    }

    public function testPersonalColumnsOfARowReportAreRecordedAsAnExport(): void
    {
        $safety = $this->course('Safety');
        $this->assign($safety, 2, '2026-10-05 09:00:00');
        $admin = $this->makeAdmin();

        $this->ask(['dataset' => 'assignments', 'columns' => ['course_title', 'status']], $admin)->assertOk();
        $this->assertSame(0, DB::table('compliance_exports')->count());

        $rows = $this->ask(['dataset' => 'assignments', 'columns' => ['user_name', 'user_email', 'status']], $admin)->assertOk()->json('data.rows');
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('@', $rows[0]['user_email']);
        $log = DB::table('compliance_exports')->first();
        $this->assertSame(['assignments', $admin->getKey(), 2], [$log->dataset, $log->user_id, $log->row_count]);
    }

    public function testEveryDatasetOfThePackageAnswersAQueryOnAnEmptyTenant(): void
    {
        foreach ([
            ['dataset' => 'assignments', 'groupBy' => ['status'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]],
            ['dataset' => 'certificates', 'groupBy' => [['column' => 'issued_at', 'bucket' => 'year']], 'aggregates' => [['fn' => 'count', 'as' => 'n']]],
            ['dataset' => 'audit', 'groupBy' => ['action'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]],
        ] as $definition) {
            $this->ask($definition)->assertOk()->assertJsonPath('data.rows', []);
        }
    }
}
