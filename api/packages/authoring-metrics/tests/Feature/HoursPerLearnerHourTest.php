<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Services\HoursPerLearnerHour;
use Ulams\AuthoringMetrics\Tests\TestCase;

/** Hours of work per learner hour (AM-6): the formula, the honesty rules, the median with n, the academy's own baseline. */
class HoursPerLearnerHourTest extends TestCase
{
    private function metric(array $over = []): CourseMetric
    {
        return new CourseMetric($over + ['generation_seconds' => 1800, 'interview_seconds' => 600, 'author_minutes' => 90, 'learner_minutes_estimate' => 60]);
    }

    public function testTheFormulaIsWorkOverLearnerTime(): void
    {
        // (1800 s + 600 s + 90 min x 60) / (60 min x 60) = (2400 + 5400) / 3600 = 2.1667 h of work per learner hour
        $h = HoursPerLearnerHour::forMetric($this->metric());

        $this->assertSame(2.1667, $h['work_hours']);
        $this->assertSame(1.0, $h['learner_hours']);
        $this->assertSame(2.17, $h['hours_per_learner_hour']);
        $this->assertNull($h['reason']);
        $this->assertStringContainsString('author minutes x 60', $h['formula']);
    }

    public function testThereIsNoFigureWithoutRecordedAuthorTimeAndTheReasonIsSaid(): void
    {
        $h = HoursPerLearnerHour::forMetric($this->metric(['author_minutes' => null]));

        $this->assertNull($h['hours_per_learner_hour']);
        $this->assertNull($h['work_hours'], 'machine time alone is not the hours figure');
        $this->assertSame('author_time_not_recorded', $h['reason']);
    }

    public function testAZeroOfRecordedAuthorTimeIsAFigureButNoLearnerMinutesIsNot(): void
    {
        $this->assertSame(0.67, HoursPerLearnerHour::forMetric($this->metric(['author_minutes' => 0]))['hours_per_learner_hour']);
        $none = HoursPerLearnerHour::forMetric($this->metric(['learner_minutes_estimate' => 0]));
        $this->assertNull($none['hours_per_learner_hour']);
        $this->assertSame('no_learner_minutes', $none['reason']);
    }

    public function testTheMedianIsOfTheCoursesThatHaveTheFigure(): void
    {
        $this->assertNull(HoursPerLearnerHour::median([]));
        $this->assertSame(3.0, HoursPerLearnerHour::median([5.0, 1.0, 3.0]));
        $this->assertSame(2.5, HoursPerLearnerHour::median([4.0, 1.0, 2.0, 3.0]));
    }

    public function testFewerThanThreeCoursesIsNotEnoughCoursesYet(): void
    {
        $at = fn (int $minutes) => $this->metric(['author_minutes' => $minutes]);

        $two = HoursPerLearnerHour::summarise([$at(60), $at(120)]);
        $this->assertSame(['not_enough_courses', 2, 2, 3, null], [$two['status'], $two['n_courses'], $two['n_with_figure'], $two['min_courses'], $two['median_hours_per_learner_hour']]);

        $three = HoursPerLearnerHour::summarise([$at(60), $at(120), $at(180)]);
        $this->assertSame('ok', $three['status']);
        $this->assertSame(3, $three['n_with_figure']);
        $this->assertSame(HoursPerLearnerHour::forMetric($at(120))['hours_per_learner_hour'], $three['median_hours_per_learner_hour']);
    }

    public function testCoursesWithoutAuthorTimeAreNotInTheMedian(): void
    {
        $with = fn (int $m) => $this->metric(['author_minutes' => $m]);
        $s = HoursPerLearnerHour::summarise([$with(60), $with(60), $with(60), $this->metric(['author_minutes' => null]), $this->metric(['author_minutes' => null])]);

        $this->assertSame(['ok', 5, 3], [$s['status'], $s['n_courses'], $s['n_with_figure']]);
        $this->assertSame('author_time_not_recorded', HoursPerLearnerHour::summarise([$this->metric(['author_minutes' => null])])['status']);
        $this->assertSame('author_time_not_recorded', HoursPerLearnerHour::summarise([])['status']);
    }

    public function testTheCourseEndpointShowsTheFigureOnlyWhenAuthorTimeWasRecorded(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $course = (int) $session->refresh()->course_id;
        $admin = $this->admin();
        Auth::forgetGuards();

        $h = $this->actingAs($admin, 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertOk()->json('data.hours');
        $this->assertNull($h['hours_per_learner_hour']);
        $this->assertSame('author_time_not_recorded', $h['reason']);

        CourseMetric::query()->whereKey($course)->update(['author_minutes' => 90]);
        $h = $this->actingAs($admin, 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->assertOk()->json('data.hours');
        $this->assertNotNull($h['hours_per_learner_hour']);
        $this->assertGreaterThan(0, $h['work_hours']);
    }

    public function testTheSummaryHasNoMedianUntilThreeCoursesHaveAuthorTime(): void
    {
        $author = $this->author();
        $courses = [];
        foreach (range(1, 3) as $i) {
            $session = $this->toApplied($author);
            $courses[] = (int) $session->refresh()->course_id;
        }
        $admin = $this->admin();
        Auth::forgetGuards();
        $get = fn () => $this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/summary')->assertOk()->json('data');

        $s = $get();
        $this->assertSame(['author_time_not_recorded', 3, 0], [$s['status'], $s['n_courses'], $s['n_with_figure']]);
        $this->assertNull($s['median_hours_per_learner_hour']);
        $this->assertNull($s['own_baseline']);

        CourseMetric::query()->whereIn('course_id', array_slice($courses, 0, 2))->update(['author_minutes' => 120]);
        $s = $get();
        $this->assertSame(['not_enough_courses', 2], [$s['status'], $s['n_with_figure']]);
        $this->assertNull($s['median_hours_per_learner_hour']);

        CourseMetric::query()->whereKey($courses[2])->update(['author_minutes' => 120]);
        $s = $get();
        $this->assertSame(['ok', 3], [$s['status'], $s['n_with_figure']]);
        $this->assertGreaterThan(0, $s['median_hours_per_learner_hour']);
        $this->assertSame(2, $s['method_version']);
    }

    public function testTheAcademysOwnBaselineIsShownAndLabelledAsItsOwn(): void
    {
        config(['authoring_metrics.baseline_hours_per_learner_hour' => 40]);
        $admin = $this->admin();

        $s = $this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/summary')->assertOk()->json('data');

        $this->assertEquals(['hours_per_learner_hour' => 40.0, 'label' => 'own_estimate'], $s['own_baseline']);
        config(['authoring_metrics.baseline_hours_per_learner_hour' => 0]);
        $this->assertNull($this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/summary')->json('data.own_baseline'));
    }

    public function testTheSummaryShowsMaintenanceApartFromTheFigure(): void
    {
        $session = $this->toApplied($this->author());
        $course = (int) $session->refresh()->course_id;
        app(CourseMetrics::class)->recompute($course);
        CourseMetric::query()->whereKey($course)->update(['cost_maintenance_micro_usd' => 1500000]);
        $admin = $this->admin();
        Auth::forgetGuards();

        $s = $this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/summary')->assertOk()->json('data');

        $this->assertSame(['courses' => 1, 'cost_micro_usd' => 1500000], $s['maintenance']);
    }

    public function testTheSummaryNeedsThePermission(): void
    {
        $this->actingAs($this->author(), 'api')->getJson('/api/admin/authoring-metrics/summary')->assertForbidden();
        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/authoring-metrics/summary?from=yesterday')->assertStatus(422);
    }

    public function testTheExportCarriesTheFigureAsAColumn(): void
    {
        $session = $this->toApplied($this->author());
        $course = (int) $session->refresh()->course_id;
        app(CourseMetrics::class)->recompute($course);
        CourseMetric::query()->whereKey($course)->update(['author_minutes' => 90]);
        $admin = $this->admin();
        Auth::forgetGuards();

        $lines = array_values(array_filter(explode("\n", $this->actingAs($admin, 'api')->get('/api/admin/authoring-metrics/courses/export')->assertOk()->streamedContent())));
        $row = array_combine(str_getcsv($lines[0], ',', '"', ''), str_getcsv($lines[1], ',', '"', ''));

        $this->assertArrayHasKey('hours_per_learner_hour', $row);
        $this->assertNotSame('', $row['hours_per_learner_hour']);
        $this->assertSame('2', $row['method_version']);
    }

    /** The product never states an industry ratio: nothing it returns, exports or documents contains one. */
    public function testNoBenchmarkAppearsInAnyOutput(): void
    {
        config(['authoring_metrics.baseline_hours_per_learner_hour' => 40]);
        $session = $this->toApplied($this->author());
        $course = (int) $session->refresh()->course_id;
        app(CourseMetrics::class)->recompute($course);
        CourseMetric::query()->whereKey($course)->update(['author_minutes' => 90]);
        $admin = $this->admin();
        Auth::forgetGuards();
        $outputs = [
            $this->actingAs($admin, 'api')->getJson("/api/admin/authoring-metrics/courses/{$course}")->getContent(),
            $this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/courses')->getContent(),
            $this->actingAs($admin, 'api')->getJson('/api/admin/authoring-metrics/summary')->getContent(),
            $this->actingAs($admin, 'api')->get('/api/admin/authoring-metrics/courses/export')->streamedContent(),
            $this->actingAs($admin, 'api')->get('/api/admin/authoring-metrics/courses/export?format=jsonl')->streamedContent(),
            HoursPerLearnerHour::FORMULA,
        ];

        foreach ($outputs as $i => $text) {
            $this->assertDoesNotMatchRegularExpression('/chapman|brandon hall|industry (average|ratio|benchmark)/i', $text, "output {$i}");
            $this->assertDoesNotMatchRegularExpression('/\b(49|79|184|490|716)\s*(h|hours)\b/i', $text, "output {$i}");
        }
    }
}
