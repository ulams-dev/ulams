<?php

namespace Ulams\AuthoringMetrics\Services;

use Ulams\AuthoringMetrics\Models\CourseMetric;

/**
 * Hours of work per hour of learner time (ADR 0194, AM-6): the academy's own figure, from its own recorded data.
 *
 *     (generation seconds + interview seconds + author minutes x 60) / (learner minutes x 60)
 *
 * Shown only when author time was recorded for the course and the course has learner minutes; otherwise there is no
 * figure and the reason is said. No industry ratio appears anywhere in the product, its exports or its documents.
 * The tenant-wide figure is a median over the courses that have the figure, with how many (`n`); fewer than three
 * courses is "not enough courses yet". An academy may enter its own earlier figure; it is shown next to the measured one
 * and labelled as the academy's own estimate. Learner minutes are the declared minutes of the lessons.
 */
final class HoursPerLearnerHour
{
    public const MIN_COURSES = 3;

    public const STATUS_OK = 'ok';

    public const STATUS_NOT_ENOUGH = 'not_enough_courses';

    public const STATUS_NO_AUTHOR_TIME = 'author_time_not_recorded';

    public const FORMULA = '(generation seconds + interview seconds + author minutes x 60) / (learner minutes x 60)';

    /**
     * @return array{work_hours:float|null,learner_hours:float,hours_per_learner_hour:float|null,reason:string|null,formula:string}
     */
    public static function forMetric(CourseMetric $m): array
    {
        $learnerHours = (int) $m->learner_minutes_estimate / 60;
        $base = ['learner_hours' => round($learnerHours, 4), 'formula' => self::FORMULA];
        if ($m->author_minutes === null) {
            return $base + ['work_hours' => null, 'hours_per_learner_hour' => null, 'reason' => 'author_time_not_recorded'];
        }
        $work = ((float) $m->generation_seconds + (float) $m->interview_seconds + (float) $m->author_minutes * 60) / 3600;
        if ($learnerHours <= 0) {
            return $base + ['work_hours' => round($work, 4), 'hours_per_learner_hour' => null, 'reason' => 'no_learner_minutes'];
        }

        return $base + ['work_hours' => round($work, 4), 'hours_per_learner_hour' => round($work / $learnerHours, 2), 'reason' => null];
    }

    /** @param array<int,float> $values */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return round($n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2, 2);
    }

    /**
     * @param iterable<CourseMetric> $metrics
     * @return array{status:string,n_courses:int,n_with_figure:int,min_courses:int,median_hours_per_learner_hour:float|null}
     */
    public static function summarise(iterable $metrics): array
    {
        $courses = 0;
        $figures = [];
        $withAuthorTime = 0;
        foreach ($metrics as $m) {
            $courses++;
            $withAuthorTime += $m->author_minutes === null ? 0 : 1;
            $h = self::forMetric($m)['hours_per_learner_hour'];
            if ($h !== null) {
                $figures[] = $h;
            }
        }
        $status = $withAuthorTime === 0 ? self::STATUS_NO_AUTHOR_TIME : (count($figures) < self::MIN_COURSES ? self::STATUS_NOT_ENOUGH : self::STATUS_OK);

        return [
            'status' => $status, 'n_courses' => $courses, 'n_with_figure' => count($figures), 'min_courses' => self::MIN_COURSES,
            'median_hours_per_learner_hour' => $status === self::STATUS_OK ? self::median($figures) : null,
        ];
    }
}
