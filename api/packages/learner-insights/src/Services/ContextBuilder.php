<?php

namespace Ulams\LearnerInsights\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Services\ElementLookup;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Models\CourseSettings;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Scoring\CohortStats;
use Ulams\LearnerInsights\Scoring\CourseStructure;
use Ulams\LearnerInsights\Scoring\LearnerCourseContext;
use Ulams\LearnerInsights\Scoring\SignalWindow;
use Ulams\LearnerInsights\Scoring\Thresholds;

/** Reads the database once and hands the scorer a plain context (the scorer itself touches nothing). */
class ContextBuilder
{
    public const WINDOW_DAYS = 60;

    public function __construct(private readonly ElementLookup $lookup)
    {
    }

    public function build(int $userId, int $courseId, ?CarbonImmutable $now = null): LearnerCourseContext
    {
        $now ??= CarbonImmutable::now();
        $signals = Signal::query()->where('user_id', $userId)->where('course_id', $courseId)
            ->where('occurred_at', '>=', $now->subDays(self::WINDOW_DAYS))
            ->orderBy('occurred_at')->orderBy('id')->get();

        $enrolment = DB::table('course_user')->where('user_id', $userId)->where('course_id', $courseId)->first();
        $structure = new CourseStructure(
            $enrolment !== null,
            $enrolment !== null && (bool) ($enrolment->finished ?? false),
            $enrolment?->created_at ? CarbonImmutable::parse($enrolment->created_at) : null,
            $this->labels($signals),
        );

        return new LearnerCourseContext($userId, $courseId, $now, new SignalWindow($signals), $structure, $this->cohort($courseId), $this->thresholds($courseId));
    }

    public function thresholds(int $courseId): Thresholds
    {
        $overrides = CourseSettings::query()->find($courseId)?->rules ?? [];

        return Thresholds::make((array) config('learner_insights.rules'), $overrides);
    }

    private function cohort(int $courseId): CohortStats
    {
        $rows = DB::table('learner_insights_element_stats')->where('course_id', $courseId)->get();

        return new CohortStats($rows->mapWithKeys(fn ($r) => [$r->element_key => ['median_seconds' => (float) $r->median_seconds, 'learners' => (int) $r->learners]])->all());
    }

    /** @return array<string,string> */
    private function labels($signals): array
    {
        $labels = [];
        $titles = Topic::query()->whereIn('id', $signals->pluck('topic_id')->filter()->unique())->pluck('title', 'id');
        foreach ($signals->unique('element_key') as $signal) {
            if ($signal->element_key === null) {
                continue;
            }
            $label = $signal->element_id && $signal->session_id ? $this->lookup->label($signal->session_id, $signal->element_id) : null;
            if ($label === null) {
                $title = $titles[$signal->topic_id] ?? null;
                $label = $title === null ? $signal->element_key : (str_starts_with($signal->element_key, 'question:') ? 'a question in "' . $title . '"' : $title);
            }
            $labels[$signal->element_key] = $label;
        }

        return $labels;
    }
}
