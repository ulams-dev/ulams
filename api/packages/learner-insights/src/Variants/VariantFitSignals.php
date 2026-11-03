<?php

namespace Ulams\LearnerInsights\Variants;

use Illuminate\Support\Facades\DB;
use Ulams\CourseVariants\Contracts\FitSignals;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\LearnerInsights\Models\LearnerStatus;
use Ulams\LearnerInsights\Scoring\Reason;
use Ulams\LearnerInsights\Scoring\Status;
use Ulams\LearnerInsights\Services\Eligibility;
use Ulams\LearnerInsights\Signals\SignalType;

/**
 * Two transparent rules that tell whether the variant a learner is in fits them (ADR 0192, plan section 11). They only
 * ever produce a hint the learner may dismiss; nothing switches variant, grades or gates anything on them.
 *
 *  - too hard: the learner's course status is "struggling" (the existing Learner Insights rules) while they have not
 *    moved past the first modules of the course.
 *  - too easy: they finished the first modules, passed every quiz there at the first attempt, and needed under half the
 *    time of the cohort's median on each of those modules (the cohort must be big enough to say anything).
 *
 * "First modules" is `learner_insights.variant_fit.modules` (2). Reasons are message keys and numbers, rendered in the
 * learner's language like every other Learner Insights reason. Respects the module switch and the learner's opt-out.
 */
final class VariantFitSignals implements FitSignals
{
    public function __construct(private readonly Eligibility $eligibility)
    {
    }

    public function assess(int $userId, int $courseId): ?array
    {
        if (!$this->eligibility->allowed($userId)) {
            return null;
        }
        $modules = max(1, (int) config('learner_insights.variant_fit.modules', 2));
        $lessons = DB::table('lessons')->where('course_id', $courseId)->where('active', true)->orderBy('order')->orderBy('id')->limit($modules)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($lessons === []) {
            return null;
        }
        $topics = DB::table('topics')->whereIn('lesson_id', $lessons)->where('active', true)->get(['id', 'lesson_id']);
        if ($topics->isEmpty()) {
            return null;
        }

        return $this->tooHard($userId, $courseId, $lessons, $modules) ?? $this->tooEasy($userId, $courseId, $lessons, $topics->all(), $modules);
    }

    /** @param array<string,scalar> $params @return array{rule:string,messageKey:string,params:array,message:string} the key and numbers, plus the text in the current language */
    private function reason(string $rule, array $params): array
    {
        $reason = new Reason($rule, "insights.reason.{$rule}", $params);

        return ['rule' => $rule, 'messageKey' => $reason->messageKey, 'params' => $params, 'message' => $reason->message()];
    }

    /** @param int[] $lessons */
    private function tooHard(int $userId, int $courseId, array $lessons, int $modules): ?array
    {
        $status = LearnerStatus::query()->where('user_id', $userId)->where('course_id', $courseId)->where('element_key', '')->first();
        if ($status === null || $status->status !== Status::STRUGGLING) {
            return null;
        }
        $beyond = DB::table('course_progress')->join('topics', 'topics.id', '=', 'course_progress.topic_id')->join('lessons', 'lessons.id', '=', 'topics.lesson_id')
            ->where('course_progress.user_id', $userId)->where('lessons.course_id', $courseId)->whereNotIn('lessons.id', $lessons)->where('course_progress.status', 1)->exists();
        if ($beyond) {
            return null; // struggling later in the course says nothing about the variant: the learner got through the start
        }

        return ['direction' => 'easier', 'reasons' => [$this->reason('variant_too_hard', ['modules' => $modules])]];
    }

    /**
     * @param int[] $lessons
     * @param array<int,object> $topics
     */
    private function tooEasy(int $userId, int $courseId, array $lessons, array $topics, int $modules): ?array
    {
        $topicIds = array_map(fn ($t) => (int) $t->id, $topics);
        $done = DB::table('course_progress')->where('user_id', $userId)->whereIn('topic_id', $topicIds)->where('status', 1)->pluck('seconds', 'topic_id');
        if ($done->count() < count($topicIds)) {
            return null; // not through the first modules yet
        }
        if (!$this->allQuizzesPassedFirstTime($userId, $courseId, $topicIds)) {
            return null;
        }
        $ratio = (float) config('learner_insights.variant_fit.time_ratio', 0.5);
        $minCohort = (int) config('learner_insights.variant_fit.min_cohort', 10);
        $fastest = 0.0;
        foreach ($lessons as $lessonId) {
            $ids = array_values(array_map(fn ($t) => (int) $t->id, array_filter($topics, fn ($t) => (int) $t->lesson_id === $lessonId)));
            $mine = (float) $done->only($ids)->sum();
            $median = $this->cohortMedian($lessonId, $ids, $userId, $minCohort);
            if ($median === null || $median <= 0 || $mine >= $ratio * $median) {
                return null; // no cohort to compare with, or not clearly faster than most learners
            }
            $fastest = max($fastest, $mine / $median);
        }

        return ['direction' => 'harder', 'reasons' => [$this->reason('variant_too_easy', ['modules' => $modules, 'percent' => (int) round($fastest * 100)])]];
    }

    /** @param int[] $topicIds every quiz of these topics must have been attempted, and passed at the first attempt */
    private function allQuizzesPassedFirstTime(int $userId, int $courseId, array $topicIds): bool
    {
        $quizTopics = DB::table('topics')->whereIn('id', $topicIds)->where('topicable_type', (new GiftQuiz())->getMorphClass())->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($quizTopics === []) {
            return false; // no check to have passed: nothing to judge by
        }
        $first = DB::table('learner_signals')->where('user_id', $userId)->where('course_id', $courseId)->where('type', SignalType::QUIZ_ATTEMPT_FINISHED)->whereIn('topic_id', $quizTopics)->orderBy('occurred_at')->orderBy('id')->get(['topic_id', 'data']);
        $firstByTopic = [];
        foreach ($first as $row) {
            $firstByTopic[(int) $row->topic_id] ??= (array) json_decode((string) $row->data, true);
        }
        foreach ($quizTopics as $topicId) {
            $data = $firstByTopic[$topicId] ?? null;
            if ($data === null || (int) ($data['attempt_no'] ?? 1) !== 1 || empty($data['passed'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Median time the other learners who finished the module's topics spent on them; null below `$minCohort` learners.
     *
     * @param int[] $topicIds
     */
    private function cohortMedian(int $lessonId, array $topicIds, int $userId, int $minCohort): ?float
    {
        $rows = DB::table('course_progress')->whereIn('topic_id', $topicIds)->where('status', 1)->where('user_id', '!=', $userId)
            ->selectRaw('user_id, SUM(seconds) AS total, COUNT(*) AS n')->groupBy('user_id')->get()->filter(fn ($r) => (int) $r->n >= count($topicIds));
        if ($rows->count() < $minCohort) {
            return null;
        }
        $totals = $rows->pluck('total')->map(fn ($v) => (float) $v)->sort()->values();
        $mid = intdiv($totals->count(), 2);

        return $totals->count() % 2 === 1 ? $totals[$mid] : ($totals[$mid - 1] + $totals[$mid]) / 2;
    }
}
