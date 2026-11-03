<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Reporting\AuthoringDataset;
use Ulams\AuthoringMetrics\Tests\TestCase;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;
use Ulams\Reporting\Tests\Concerns\AssertsDatasetContract;

/** The authoring metric as a report dataset (RB-1): registered, declared honestly, and equal to the snapshot. */
class AuthoringDatasetTest extends TestCase
{
    use AssertsDatasetContract;

    public function testItIsRegisteredWithTheMetricPermission(): void
    {
        $dataset = $this->app->make(DatasetRegistry::class)->get('authoring');

        $this->assertInstanceOf(AuthoringDataset::class, $dataset);
        $this->assertSame('authoring_metrics_read', $dataset->permission());
        $this->assertNull($dataset->subjectColumn(), 'a course is not a learner: nothing to suppress');
        $this->assertSame(['course_created_at', 'computed_at'], $dataset->dateColumns());
    }

    public function testTheBaseQuerySelectsTheDeclaredColumns(): void
    {
        $this->assertDatasetContract(new AuthoringDataset());
    }

    public function testNoColumnIsPersonal(): void
    {
        $this->assertSame([], array_filter((new AuthoringDataset())->columns(), fn (Column $c) => $c->personal));
    }

    public function testARowEqualsTheSnapshotOfTheCourse(): void
    {
        $session = $this->toApplied($this->author(), 'coffee-brewing.md', ['quiz', 'final']);
        $course = (int) $session->refresh()->course_id;
        $metric = CourseMetric::query()->findOrFail($course);

        $rows = (new AuthoringDataset())->baseQuery(new ReportContext(null))->get();

        $this->assertCount(1, $rows);
        $row = (array) $rows[0];
        $this->assertSame($course, (int) $row['course_id']);
        $this->assertNotNull($row['course_title']);
        $this->assertEqualsWithDelta((float) $metric->generation_seconds, (float) $row['generation_seconds'], 0.0005);
        $this->assertEqualsWithDelta($metric->cost_build_micro_usd / 1000000, (float) $row['cost_build_usd'], 0.0000001);
        $this->assertEqualsWithDelta($metric->cost_maintenance_micro_usd / 1000000, (float) $row['cost_maintenance_usd'], 0.0000001);
        $this->assertGreaterThan(0, (int) $row['llm_calls']);
        $this->assertSame((int) $metric->ai_elements, (int) $row['ai_elements']);
        $this->assertSame((int) $metric->blocks, (int) $row['cited_blocks'], 'every block of the build cites its sources');
        $this->assertNull($row['author_minutes'], 'review time that was not recorded is empty, never zero');
    }

    public function testAReportOverTheMetricAveragesCostAndCountsCourses(): void
    {
        $this->seed(\Ulams\Reporting\Database\Seeders\ReportingPermissionSeeder::class);
        $session = $this->toApplied($this->author(), 'coffee-brewing.md', ['quiz', 'final']);
        $metric = CourseMetric::query()->findOrFail((int) $session->refresh()->course_id);

        $response = $this->actingAs($this->admin(), 'api')->postJson('/api/admin/reporting/query', [
            'dataset' => 'authoring',
            'aggregates' => [['fn' => 'count', 'as' => 'courses'], ['fn' => 'sum', 'column' => 'cost_build_usd', 'as' => 'build_cost'], ['fn' => 'max', 'column' => 'lessons', 'as' => 'most_lessons']],
            'dateRange' => ['column' => 'computed_at', 'preset' => 'last_7_days'],
        ])->assertOk();

        $row = $response->json('data.rows.0');
        $this->assertSame(1, $row['courses']);
        $this->assertEqualsWithDelta($metric->cost_build_micro_usd / 1000000, $row['build_cost'], 0.0000001);
        $this->assertSame((int) $metric->lessons, $row['most_lessons']);
        $this->assertNull($response->json('meta.min_group_size'), 'a course is not a learner: no suppression');
        $this->actingAs($this->author(), 'api')->postJson('/api/admin/reporting/query', ['dataset' => 'authoring', 'columns' => ['course_id']])->assertForbidden();
    }
}
