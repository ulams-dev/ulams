<?php

namespace Ulams\LearnerInsights\Scoring;

use Illuminate\Support\Collection;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Signals\SignalType;

/**
 * The five transparent rules of ADR 0058. Pure: it reads the context and returns an assessment
 * with a reason for everything it concludes; it has no database access and no clock of its own.
 */
final class RuleBasedScorer implements RiskScorer
{
    public const VERSION = '2';

    public function key(): string
    {
        return 'rules';
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function assess(LearnerCourseContext $ctx): Assessment
    {
        // finished or no longer enrolled: always on track (statuses are kept for analytics)
        if ($ctx->course->finished || !$ctx->course->enrolled) {
            return new Assessment(Status::ON_TRACK, [], []);
        }

        /** @var array<string,Reason[]> $strong */
        $strong = [];
        /** @var array<string,Reason[]> $weak */
        $weak = [];
        foreach ($this->failedAttempts($ctx) as $key => $reasons) {
            $strong[$key] = array_merge($strong[$key] ?? [], $reasons);
        }
        foreach ($this->slowElements($ctx) as $key => $reasons) {
            $strong[$key] = array_merge($strong[$key] ?? [], $reasons);
        }
        foreach ($this->abandonedElements($ctx) as $key => $reasons) {
            $strong[$key] = array_merge($strong[$key] ?? [], $reasons);
        }
        foreach ($this->repetition($ctx) as $key => $reasons) {
            $weak[$key] = $reasons;
        }

        $elements = [];
        foreach ($strong as $key => $reasons) {
            $elements[$key] = new ElementAssessment($key, Status::STRUGGLING, array_merge($reasons, $weak[$key] ?? []));
        }
        // repetition alone is low weight: kept on the element, but it does not move the course status
        foreach ($weak as $key => $reasons) {
            $elements[$key] ??= new ElementAssessment($key, Status::STRUGGLING, $reasons, true);
        }

        $courseReasons = [];
        $inactiveDays = $this->inactiveDays($ctx);
        $inactivityFires = $ctx->thresholds->enabled('inactivity') && $inactiveDays >= $ctx->thresholds->get('inactivity', 'inactive_days');
        if ($inactivityFires) {
            $courseReasons[] = new Reason('inactivity', 'insights.reason.inactivity', ['days' => $inactiveDays]);
        }

        $strugglingCount = count($strong);
        $atRiskByStruggle = $strugglingCount >= $ctx->thresholds->get('inactivity', 'struggling_elements_at_risk') && $inactiveDays >= 3;

        if ($inactivityFires || $atRiskByStruggle) {
            if ($atRiskByStruggle && !$inactivityFires) {
                $courseReasons[] = new Reason('struggling_elements', 'insights.reason.struggling_elements', ['count' => $strugglingCount, 'days' => $inactiveDays]);
            }
            $course = Status::AT_RISK;
        } elseif ($strugglingCount > 0) {
            $course = Status::STRUGGLING;
        } else {
            $course = Status::ON_TRACK;
        }

        return new Assessment($course, $elements, $courseReasons);
    }

    private function inactiveDays(LearnerCourseContext $ctx): int
    {
        $last = $ctx->signals->lastActivity() ?? $ctx->course->enrolledAt;
        if ($last === null) {
            return 0;
        }

        return (int) floor($last->diffInSeconds($ctx->now, true) / 86400);
    }

    /** @return array<string,Reason[]> */
    private function failedAttempts(LearnerCourseContext $ctx): array
    {
        $t = $ctx->thresholds;
        if (!$t->enabled('failed_attempts')) {
            return [];
        }
        $out = [];

        // wrong answers to the same question since the learner last got it right
        $byQuestion = $ctx->signals->ofType(SignalType::QUIZ_ANSWER)->groupBy(fn (Signal $s) => $s->data['question_id'] ?? $s->element_key);
        foreach ($byQuestion as $answers) {
            $failures = 0;
            foreach ($answers as $answer) {
                $failures = ($answer->data['correct'] ?? false) ? 0 : $failures + 1;
            }
            if ($failures >= $t->get('failed_attempts', 'failed_answers')) {
                $key = $answers->last()->element_key;
                $out[$key][] = new Reason('failed_attempts', 'insights.reason.failed_attempts', ['count' => $failures, 'label' => $ctx->course->label($key)], $key);
            }
        }

        // failed attempts of the same quiz or code exercise since the last pass (practice attempts count: this is
        // information for the learner and the teacher, never a grade)
        $byQuiz = $ctx->signals->ofType(SignalType::QUIZ_ATTEMPT_FINISHED, SignalType::CODE_ATTEMPT_FINISHED)->groupBy('element_key');
        foreach ($byQuiz as $key => $attempts) {
            $failures = 0;
            foreach ($attempts as $attempt) {
                $failures = ($attempt->data['passed'] ?? false) ? 0 : $failures + 1;
            }
            if ($failures >= $t->get('failed_attempts', 'failed_quiz_attempts')) {
                $out[$key][] = new Reason('failed_attempts', 'insights.reason.failed_quiz_attempts', ['count' => $failures, 'label' => $ctx->course->label($key)], $key);
            }
        }

        return $out;
    }

    /** @return array<string,Reason[]> */
    private function slowElements(LearnerCourseContext $ctx): array
    {
        $t = $ctx->thresholds;
        if (!$t->enabled('slow_element')) {
            return [];
        }
        $out = [];
        $seconds = $ctx->signals->ofType(SignalType::TOPIC_PROGRESSED)->groupBy('element_key')->map(fn (Collection $c) => (float) $c->sum('value'));
        foreach ($seconds as $key => $spent) {
            if ($ctx->signals->completed($key)) {
                continue;
            }
            $median = $ctx->cohort->medianSeconds($key, (int) $t->get('slow_element', 'min_cohort'));
            $factor = (float) $t->get('slow_element', 'slow_factor');
            if ($median !== null && $spent > $factor * $median) {
                $out[$key][] = new Reason('slow_element', 'insights.reason.slow_element', ['factor' => (int) round($spent / $median), 'label' => $ctx->course->label($key)], $key);
            }
        }

        return $out;
    }

    /** @return array<string,Reason[]> */
    private function abandonedElements(LearnerCourseContext $ctx): array
    {
        $t = $ctx->thresholds;
        if (!$t->enabled('abandoned_element')) {
            return [];
        }
        $out = [];
        $activity = $ctx->signals->activity();
        foreach ($ctx->signals->ofType(SignalType::TOPIC_OPENED)->groupBy('element_key') as $key => $opened) {
            if ($ctx->signals->completed($key)) {
                continue;
            }
            $first = $opened->min('occurred_at');
            $days = (int) floor($first->diffInSeconds($ctx->now, true) / 86400);
            if ($days < $t->get('abandoned_element', 'abandoned_days')) {
                continue;
            }
            // only abandoned while the learner was busy elsewhere in the course
            $activeElsewhere = $activity->contains(fn (Signal $s) => $s->element_key !== $key && $s->occurred_at->greaterThan($first));
            if ($activeElsewhere) {
                $out[$key][] = new Reason('abandoned_element', 'insights.reason.abandoned_element', ['days' => $days, 'label' => $ctx->course->label($key)], $key);
            }
        }

        return $out;
    }

    /** @return array<string,Reason[]> */
    private function repetition(LearnerCourseContext $ctx): array
    {
        $t = $ctx->thresholds;
        if (!$t->enabled('repetition')) {
            return [];
        }
        $since = $ctx->now->subDays(7);
        $recent = $ctx->signals->filter(fn (Signal $s) => $s->occurred_at->greaterThanOrEqualTo($since));
        $out = [];

        foreach ($recent->whereIn('type', [SignalType::MEDIA_REPLAYED, SignalType::MEDIA_SEEKED_BACK])->groupBy('element_key') as $key => $replays) {
            if ($replays->count() >= $t->get('repetition', 'replays') && !$ctx->signals->completed($key)) {
                $out[$key][] = new Reason('repetition', 'insights.reason.replays', ['count' => $replays->count(), 'label' => $ctx->course->label($key)], $key);
            }
        }
        foreach ($recent->where('type', SignalType::TOPIC_OPENED)->filter(fn (Signal $s) => $s->data['revisit'] ?? false)->groupBy('element_key') as $key => $visits) {
            if ($visits->count() >= $t->get('repetition', 'revisits')) {
                $out[$key][] = new Reason('repetition', 'insights.reason.revisits', ['count' => $visits->count(), 'label' => $ctx->course->label($key)], $key);
            }
        }

        return $out;
    }
}
