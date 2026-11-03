<?php

namespace Ulams\AuthoringMetrics\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\AuthoringMetrics\Models\CourseMetric;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Services\ReviewTime;
use Ulams\AuthoringMetrics\Tests\TestCase;

/** Anonymous review time (AM-5): the minute rules, the rollup, the retention, and what the tables can hold. */
class ReviewTimeTest extends TestCase
{
    private const NONCE = 'AAAAAAAAAAAAAAAAAAAAAA';

    private function time(): ReviewTime
    {
        return app(ReviewTime::class);
    }

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment, 'UTC');
    }

    public function testItIsOffByDefault(): void
    {
        $this->assertFalse($this->time()->enabled());
        config(['authoring_metrics.review_time' => true]);
        $this->assertTrue($this->time()->enabled());
    }

    public function testMinutesAreCutToTheMinuteAndStoredOncePerPageLoad(): void
    {
        $now = $this->at('2026-10-20 10:30:40');

        $stored = $this->time()->record(5, ['2026-10-20T10:29:12Z', '2026-10-20T10:29:59Z', '2026-10-20T10:30:05Z'], 'studio', self::NONCE, $now);

        $this->assertSame(2, $stored, 'two moments of one minute are one minute');
        $this->assertSame(0, $this->time()->record(5, ['2026-10-20T10:29:30Z'], 'studio', self::NONCE, $now), 'a repeat is stored once');
        $this->assertSame(['2026-10-20 10:29:00', '2026-10-20 10:30:00'], DB::table('authoring_activity_minutes')->orderBy('minute_start')->pluck('minute_start')->all());
    }

    public function testTwoPeopleInTheSameMinuteAreTwoAuthorMinutes(): void
    {
        $now = $this->at('2026-10-20 10:30:40');
        $this->time()->record(5, ['2026-10-20T10:30:05Z'], 'studio', self::NONCE, $now);
        $this->time()->record(5, ['2026-10-20T10:30:05Z'], 'studio', 'BBBBBBBBBBBBBBBBBBBBBB', $now);
        $this->time()->record(5, ['2026-10-20T10:30:05Z'], 'admin', self::NONCE, $now);

        $this->assertSame(3.0, $this->time()->minutes(5));
    }

    public function testFutureStaleAndTooManyMinutesAreRejectedWholesale(): void
    {
        $now = $this->at('2026-10-20 10:30:40');
        foreach ([
            [['2026-10-20T10:31:00Z'], 'future'],
            [['2026-10-20T10:14:59Z'], 'older than 15 minutes'],
            [['2026-10-20T10:29:00Z', '2026-10-21T10:29:00Z'], 'future, the second'],
            [array_map(fn ($i) => $this->at('2026-10-20 10:30:00')->subMinutes($i)->toIso8601String(), range(0, 10)), 'eleven minutes'],
        ] as [$minutes, $why]) {
            try {
                $this->time()->record(5, $minutes, 'studio', self::NONCE, $now);
                $this->fail("accepted: {$why}");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, DB::table('authoring_activity_minutes')->count(), 'a refused call stores nothing');
        $this->assertSame(1, $this->time()->record(5, ['2026-10-20T10:15:00Z'], 'studio', self::NONCE, $now), 'fifteen minutes old is still fine');
    }

    public function testRollupTotalsPastDaysAndDeletesRawMinutesAfterSevenDays(): void
    {
        $now = $this->at('2026-10-20 03:10:00');
        $insert = fn (string $minute, string $nonce, int $course = 5, string $surface = 'studio') => DB::table('authoring_activity_minutes')->insert(['course_id' => $course, 'minute_start' => $minute, 'tab_nonce' => $nonce, 'surface' => $surface]);
        $insert('2026-10-19 09:00:00', self::NONCE);
        $insert('2026-10-19 09:01:00', self::NONCE);
        $insert('2026-10-19 09:00:00', 'BBBBBBBBBBBBBBBBBBBBBB');
        $insert('2026-10-19 11:00:00', self::NONCE, 5, 'admin');
        $insert('2026-10-19 09:00:00', self::NONCE, 6);
        $insert('2026-10-20 00:30:00', self::NONCE); // today: not rolled up yet
        $insert('2026-10-12 23:59:00', self::NONCE); // older than seven days

        $result = $this->time()->rollup($now);

        $this->assertSame(4, $result['days'], 'course 5 studio and admin, course 6, and the old day');
        $this->assertSame(1, $result['deleted']);
        $this->assertEqualsCanonicalizing([5, 6], $result['courses']);
        $daily = DB::table('authoring_course_daily')->orderBy('course_id')->orderBy('surface')->get(['course_id', 'day', 'surface', 'author_minutes'])->map(fn ($r) => (array) $r)->all();
        $this->assertContains(['course_id' => 5, 'day' => '2026-10-19', 'surface' => 'studio', 'author_minutes' => 3], $daily);
        $this->assertContains(['course_id' => 5, 'day' => '2026-10-19', 'surface' => 'admin', 'author_minutes' => 1], $daily);
        $this->assertContains(['course_id' => 6, 'day' => '2026-10-19', 'surface' => 'studio', 'author_minutes' => 1], $daily);
        $this->assertSame(0, DB::table('authoring_activity_minutes')->where('minute_start', '<', '2026-10-13 00:00:00')->count());
        $this->assertSame(1, DB::table('authoring_activity_minutes')->where('minute_start', '>=', '2026-10-20')->count(), 'today stays raw');

        // a second run changes nothing, and the old day's total outlives its raw minutes
        $again = $this->time()->rollup($now);
        $this->assertSame(0, $again['deleted']);
        $this->assertContains(['course_id' => 5, 'day' => '2026-10-12', 'surface' => 'studio', 'author_minutes' => 1], DB::table('authoring_course_daily')->get(['course_id', 'day', 'surface', 'author_minutes'])->map(fn ($r) => (array) $r)->all());
    }

    public function testAuthorMinutesAreTheDailyTotalsPlusRawMinutesNotRolledUpAndNullWhenNothingWasRecorded(): void
    {
        $this->assertNull($this->time()->minutes(5), 'not recorded is not zero');
        DB::table('authoring_course_daily')->insert(['course_id' => 5, 'day' => '2026-10-19', 'surface' => 'studio', 'author_minutes' => 30]);
        DB::table('authoring_activity_minutes')->insert([
            ['course_id' => 5, 'minute_start' => '2026-10-19 09:00:00', 'tab_nonce' => self::NONCE, 'surface' => 'studio'], // already in the total
            ['course_id' => 5, 'minute_start' => '2026-10-20 09:00:00', 'tab_nonce' => self::NONCE, 'surface' => 'studio'],
            ['course_id' => 5, 'minute_start' => '2026-10-20 09:01:00', 'tab_nonce' => self::NONCE, 'surface' => 'studio'],
        ]);

        $this->assertSame(32.0, $this->time()->minutes(5));
        $this->assertNull($this->time()->minutes(6));
    }

    public function testTheTablesCanHoldNoPersonOrDevice(): void
    {
        $this->assertEqualsCanonicalizing(['course_id', 'minute_start', 'tab_nonce', 'surface'], Schema::getColumnListing('authoring_activity_minutes'));
        $this->assertEqualsCanonicalizing(['course_id', 'day', 'surface', 'author_minutes'], Schema::getColumnListing('authoring_course_daily'));
    }

    public function testTheSnapshotCarriesTheReviewTime(): void
    {
        $session = $this->toApplied($this->author());
        $courseId = (int) $session->refresh()->course_id;
        $this->assertNull(app(CourseMetrics::class)->recompute($courseId)->author_minutes);

        $this->time()->record($courseId, [now()->subMinutes(2)->toIso8601String(), now()->subMinutes(3)->toIso8601String()], 'studio', self::NONCE);

        $this->assertSame('2.0', (string) app(CourseMetrics::class)->recompute($courseId)->author_minutes);
    }

    public function testDeletingEverythingKeepsGenerationTimeCostAndCounts(): void
    {
        $session = $this->toApplied($this->author());
        $courseId = (int) $session->refresh()->course_id;
        $this->time()->record($courseId, [now()->subMinute()->toIso8601String()], 'studio', self::NONCE);
        $before = app(CourseMetrics::class)->recompute($courseId);
        $this->assertNotNull($before->author_minutes);

        $deleted = $this->time()->deleteAll();
        $after = app(CourseMetrics::class)->recompute($courseId);

        $this->assertSame(1, $deleted['minutes']);
        $this->assertNull($after->author_minutes);
        $this->assertSame((string) $before->generation_seconds, (string) $after->generation_seconds);
        $this->assertSame((int) $before->cost_build_micro_usd, (int) $after->cost_build_micro_usd);
        $this->assertSame((int) $before->lessons, (int) $after->lessons);
        $this->assertSame(1, CourseMetric::query()->count());
    }

    public function testTheRollupCommandRefreshesTheSnapshotsOfCoursesItChanged(): void
    {
        $session = $this->toApplied($this->author());
        $courseId = (int) $session->refresh()->course_id;
        DB::table('authoring_activity_minutes')->insert(['course_id' => $courseId, 'minute_start' => now('UTC')->subDays(2)->toDateTimeString(), 'tab_nonce' => self::NONCE, 'surface' => 'studio']);

        $this->artisan('authoring-metrics:rollup')->expectsOutputToContain('Rolled up 1 course days, deleted 0 raw minutes.')->assertSuccessful();

        $this->assertSame('1.0', (string) CourseMetric::query()->find($courseId)->author_minutes);
        $this->assertSame(1, (int) DB::table('authoring_course_daily')->where('course_id', $courseId)->sum('author_minutes'));
    }
}
