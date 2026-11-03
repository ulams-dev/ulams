<?php

namespace Ulams\AuthoringMetrics\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous review time of a course (ADR 0194, AM-5): active minutes in the studio and in the admin course editor,
 * counted in whole minutes. Nothing here knows who the person is.
 *
 *  - `record`: stores the minutes a browser reports, at most 10 a call, none in the future and none older than 15
 *    minutes. The same minute from the same page load is stored once; two people in the same minute are two
 *    author-minutes.
 *  - `rollup`: totals the minutes per course, day and surface into `authoring_course_daily` and deletes raw
 *    minutes older than the retention (7 days by default). Daily totals live as long as the course.
 *  - `minutes`: author-minutes of a course: the daily totals plus the raw minutes of days not rolled up yet; null when
 *    nothing was ever recorded for the course (not recorded is not zero).
 */
class ReviewTime
{
    public const MAX_MINUTES_PER_CALL = 10;

    public const MAX_AGE_MINUTES = 15;

    public const SURFACES = ['studio', 'admin'];

    public function enabled(): bool
    {
        return (bool) config('authoring_metrics.review_time');
    }

    /**
     * @param array<int,string> $minutes ISO 8601 moments; each is cut to its minute
     * @return int the number of minutes stored (a repeated minute is stored once)
     * @throws \InvalidArgumentException when a minute is in the future or too old, or there are too many
     */
    public function record(int $courseId, array $minutes, string $surface, string $nonce, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');
        if (count($minutes) > self::MAX_MINUTES_PER_CALL) {
            throw new \InvalidArgumentException('At most ' . self::MAX_MINUTES_PER_CALL . ' minutes per call.');
        }
        $rows = [];
        foreach ($minutes as $moment) {
            $start = CarbonImmutable::parse($moment)->utc()->startOfMinute();
            if ($start->greaterThan($now)) {
                throw new \InvalidArgumentException('A minute is in the future.');
            }
            if ($start->lessThan($now->subMinutes(self::MAX_AGE_MINUTES)->startOfMinute())) {
                throw new \InvalidArgumentException('A minute is older than ' . self::MAX_AGE_MINUTES . ' minutes.');
            }
            $rows[$start->toDateTimeString()] = ['course_id' => $courseId, 'minute_start' => $start->toDateTimeString(), 'tab_nonce' => $nonce, 'surface' => $surface];
        }

        return $rows === [] ? 0 : DB::table('authoring_activity_minutes')->insertOrIgnore(array_values($rows));
    }

    /**
     * @return array{days:int,deleted:int,courses:array<int,int>} days written, raw minutes deleted, courses whose totals changed
     */
    public function rollup(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $today = $now->startOfDay()->toDateTimeString();
        $groups = DB::table('authoring_activity_minutes')->where('minute_start', '<', $today)
            ->selectRaw('course_id, date(minute_start) as day, surface, count(*) as minutes')->groupBy('course_id', DB::raw('date(minute_start)'), 'surface')->get();
        $courses = [];
        foreach ($groups as $g) {
            DB::table('authoring_course_daily')->upsert(
                [['course_id' => $g->course_id, 'day' => $g->day, 'surface' => $g->surface, 'author_minutes' => (int) $g->minutes]],
                ['course_id', 'day', 'surface'],
                ['author_minutes'],
            );
            $courses[(int) $g->course_id] = (int) $g->course_id;
        }
        $deleted = DB::table('authoring_activity_minutes')
            ->where('minute_start', '<', $now->subDays((int) config('authoring_metrics.raw_minutes_retention_days', 7))->startOfDay()->toDateTimeString())->delete();

        return ['days' => $groups->count(), 'deleted' => $deleted, 'courses' => array_values($courses)];
    }

    /** Author-minutes of a course, or null when nothing was recorded. */
    public function minutes(int $courseId): ?float
    {
        $hasDaily = DB::table('authoring_course_daily')->where('course_id', $courseId)->exists();
        $hasRaw = DB::table('authoring_activity_minutes')->where('course_id', $courseId)->exists();
        if (!$hasDaily && !$hasRaw) {
            return null;
        }
        $rolled = (int) DB::table('authoring_course_daily')->where('course_id', $courseId)->sum('author_minutes');
        // raw minutes of days that are not rolled up yet (the rolled-up ones are in the daily totals already)
        $unrolled = (int) DB::table('authoring_activity_minutes as a')->where('a.course_id', $courseId)
            ->whereRaw('not exists (select 1 from authoring_course_daily d where d.course_id = a.course_id and d.surface = a.surface and d.day = date(a.minute_start))')->count();

        return (float) ($rolled + $unrolled);
    }

    /** "Delete recorded review time": raw minutes and daily totals of every course. @return array{minutes:int,days:int} */
    public function deleteAll(): array
    {
        return ['minutes' => DB::table('authoring_activity_minutes')->delete(), 'days' => DB::table('authoring_course_daily')->delete()];
    }
}
