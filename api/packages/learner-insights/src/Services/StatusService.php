<?php

namespace Ulams\LearnerInsights\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ulams\LearnerInsights\Events\LearnerAtRisk;
use Ulams\LearnerInsights\Events\LearnerRecovered;
use Ulams\LearnerInsights\Events\LearnerStruggling;
use Ulams\LearnerInsights\Models\LearnerStatus;
use Ulams\LearnerInsights\Scoring\Assessment;
use Ulams\LearnerInsights\Scoring\Reason;
use Ulams\LearnerInsights\Scoring\RiskScorer;
use Ulams\LearnerInsights\Scoring\Status;

/**
 * Evaluates one learner in one course and keeps the result: the current statuses, an append-only
 * history of transitions, and one event per transition (never for a repeated status). A status
 * is information only: nothing here, or anywhere else, uses it for grades, access or enrolment
 * (ADR 0061).
 */
class StatusService
{
    public function __construct(
        private readonly RiskScorer $scorer,
        private readonly ContextBuilder $contexts,
        private readonly Eligibility $eligibility,
    ) {
    }

    public function evaluate(int $userId, int $courseId, ?CarbonImmutable $now = null): ?Assessment
    {
        if (!$this->eligibility->allowed($userId)) {
            return null;
        }
        $now ??= CarbonImmutable::now();
        $assessment = $this->scorer->assess($this->contexts->build($userId, $courseId, $now));
        $events = [];

        DB::transaction(function () use ($userId, $courseId, $now, $assessment, &$events) {
            $existing = LearnerStatus::query()->where('user_id', $userId)->where('course_id', $courseId)->get()->keyBy('element_key');

            // elements: only those with a strong rule are kept (low-weight repetition alone is not flagged)
            $strong = array_filter($assessment->elements, fn ($e) => !$e->lowWeight);
            foreach ($strong as $key => $element) {
                $reasons = array_map(fn (Reason $r) => $r->toArray(), $element->reasons);
                $from = $existing[$key]->status ?? Status::ON_TRACK;
                $this->store($userId, $courseId, $key, Status::STRUGGLING, $reasons, $existing[$key] ?? null, $now);
                if ($from !== Status::STRUGGLING) {
                    $this->transition($userId, $courseId, $key, $from, Status::STRUGGLING, $reasons, $now);
                    $events[] = new LearnerStruggling($userId, $courseId, $key, $reasons);
                }
            }
            foreach ($existing as $key => $row) {
                if ($key !== '' && !isset($strong[$key])) {
                    $this->transition($userId, $courseId, $key, $row->status, Status::ON_TRACK, [], $now);
                    $row->delete();
                    $events[] = new LearnerRecovered($userId, $courseId, $key);
                }
            }

            // course level: its own reasons plus those of the struggling elements
            $reasons = array_map(fn (Reason $r) => $r->toArray(), $assessment->reasons);
            foreach ($strong as $element) {
                foreach ($element->reasons as $r) {
                    $reasons[] = $r->toArray();
                }
            }
            $from = $existing['']->status ?? Status::ON_TRACK;
            $to = $assessment->course;
            $this->store($userId, $courseId, '', $to, $reasons, $existing[''] ?? null, $now);
            if ($from !== $to) {
                $this->transition($userId, $courseId, '', $from, $to, $reasons, $now);
                if ($to === Status::AT_RISK) {
                    $events[] = new LearnerAtRisk($userId, $courseId, $reasons);
                } elseif ($to === Status::ON_TRACK) {
                    $events[] = new LearnerRecovered($userId, $courseId, null);
                }
            }
        });

        foreach ($events as $event) {
            event($event);
        }

        return $assessment;
    }

    private function store(int $userId, int $courseId, string $key, string $status, array $reasons, ?LearnerStatus $row, CarbonImmutable $now): void
    {
        $attrs = ['status' => $status, 'reasons' => $reasons, 'scorer' => $this->scorer->key(), 'scorer_version' => $this->scorer->version(), 'evaluated_at' => $now];
        if ($row === null) {
            LearnerStatus::query()->create(['user_id' => $userId, 'course_id' => $courseId, 'element_key' => $key, 'since' => $now] + $attrs);

            return;
        }
        if ($row->status !== $status) {
            $attrs['since'] = $now;
        }
        $row->update($attrs);
    }

    private function transition(int $userId, int $courseId, string $key, string $from, string $to, array $reasons, CarbonImmutable $now): void
    {
        DB::table('learner_status_transitions')->insert([
            'user_id' => $userId, 'course_id' => $courseId, 'element_key' => $key,
            'from' => $from, 'to' => $to, 'reasons' => json_encode($reasons), 'occurred_at' => $now,
        ]);
    }
}
