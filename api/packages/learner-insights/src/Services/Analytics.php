<?php

namespace Ulams\LearnerInsights\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Services\ElementLookup;
use Ulams\LearnerInsights\Models\Intervention;
use Ulams\LearnerInsights\Scoring\Reason;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LivingCourse\Services\InsightsProposalService;

/**
 * Aggregates for authors and admins (plan section 10). Every figure is a count or a rate over groups
 * of at least `min_group_size` learners: smaller groups are withheld here, on the server, not only
 * in the UI. Individual learners are only reachable through `learners()` and `timeline()`, which the
 * caller gates with a permission.
 */
class Analytics
{
    public const PERIODS = [7, 30, 90];

    public function __construct(private readonly ElementLookup $lookup)
    {
    }

    public static function minGroup(): int
    {
        return max(1, (int) config('learner_insights.min_group_size'));
    }

    /** @return array<string,mixed> */
    public function summary(int $courseId, int $days): array
    {
        $since = CarbonImmutable::now()->subDays($days);
        $min = self::minGroup();
        $enrolled = (int) DB::table('course_user')->where('course_id', $courseId)->count();
        $out = ['courseId' => $courseId, 'periodDays' => $days, 'minGroupSize' => $min, 'enrolled' => $enrolled];
        if ($enrolled < $min) {
            return $out + ['smallGroup' => true];
        }

        $activeUsers = DB::table('learner_signals')->where('course_id', $courseId)->where('occurred_at', '>=', $since)
            ->whereIn('type', [SignalType::TOPIC_OPENED, SignalType::TOPIC_PROGRESSED, SignalType::TOPIC_COMPLETED, SignalType::QUIZ_ANSWER, SignalType::QUIZ_ATTEMPT_FINISHED, SignalType::SCORM_STATUS])
            ->distinct()->count('user_id');
        $completed = (int) DB::table('course_user')->where('course_id', $courseId)->where('finished', true)->count();

        $attempts = DB::table('learner_signals')->where('course_id', $courseId)->where('type', SignalType::QUIZ_ATTEMPT_FINISHED)->where('occurred_at', '>=', $since)->get(['value', 'data']);
        $passed = $attempts->filter(fn ($a) => (json_decode((string) $a->data, true)['passed'] ?? false) === true)->count();

        $statuses = DB::table('learner_statuses')->where('course_id', $courseId)->where('element_key', '')->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return $out + [
            'smallGroup' => false,
            'learners' => ['enrolled' => $enrolled, 'active' => $activeUsers, 'completed' => $completed, 'completionRate' => round($completed / $enrolled, 4)],
            'quiz' => [
                'attempts' => $attempts->count(),
                'averageScore' => $attempts->count() ? round((float) $attempts->avg('value'), 4) : null,
                'passRate' => $attempts->count() ? round($passed / $attempts->count(), 4) : null,
            ],
            'statuses' => ['on_track' => (int) ($statuses['on_track'] ?? 0), 'struggling' => (int) ($statuses['struggling'] ?? 0), 'at_risk' => (int) ($statuses['at_risk'] ?? 0)],
            'nudges' => $this->nudges($courseId, $since),
            'remediation' => $this->remediations($courseId, $since),
        ];
    }

    /**
     * Elements ranked by the share of their active learners who struggled in the period.
     *
     * @return array{courseId:int,periodDays:int,minGroupSize:int,elements:array<int,array<string,mixed>>}
     */
    public function elements(int $courseId, int $days, string $sort = 'struggle'): array
    {
        $since = CarbonImmutable::now()->subDays($days);
        $min = self::minGroup();

        $active = DB::table('learner_signals')->where('course_id', $courseId)->where('occurred_at', '>=', $since)->whereNotNull('element_key')
            ->whereIn('type', [SignalType::TOPIC_OPENED, SignalType::TOPIC_PROGRESSED, SignalType::TOPIC_COMPLETED, SignalType::QUIZ_ANSWER])
            ->selectRaw('element_key, MAX(element_id) as element_id, MAX(session_id) as session_id, COUNT(DISTINCT user_id) as learners')->groupBy('element_key')->get();

        $struggled = DB::table('learner_status_transitions')->where('course_id', $courseId)->where('occurred_at', '>=', $since)->where('to', 'struggling')->where('element_key', '!=', '')
            ->get(['element_key', 'user_id', 'reasons'])->groupBy('element_key');
        $remediated = DB::table('learner_remediation_assignments as a')->join('learner_remediations as r', 'r.id', '=', 'a.remediation_id')
            ->where('r.course_id', $courseId)->where('a.created_at', '>=', $since)->selectRaw('r.element_id, COUNT(DISTINCT a.user_id) as learners')->groupBy('r.element_id')->pluck('learners', 'element_id');
        $interventions = Intervention::query()->where('course_id', $courseId)->whereNotNull('element_key')->where('created_at', '>=', $since)->whereIn('outcome', [Intervention::RECOVERED, Intervention::NOT_RECOVERED])
            ->get()->groupBy('element_key');

        $rows = [];
        foreach ($active as $row) {
            $label = $row->element_id && $row->session_id ? ($this->lookup->label($row->session_id, $row->element_id) ?? $row->element_key) : $row->element_key;
            if ((int) $row->learners < $min) {
                $rows[] = ['elementKey' => $row->element_key, 'label' => $label, 'hidden' => true, 'reason' => 'not_enough_learners'];
                continue;
            }
            $s = $struggled->get($row->element_key, collect());
            $strugglers = $s->pluck('user_id')->unique()->count();
            $closed = $interventions->get($row->element_key, collect());
            $recovered = $closed->where('outcome', Intervention::RECOVERED)->count();
            $rows[] = [
                'elementKey' => $row->element_key,
                'label' => $label,
                'hidden' => false,
                'activeLearners' => (int) $row->learners,
                'struggleRate' => round(min(1, $strugglers / (int) $row->learners), 4),
                'remediationRate' => round(min(1, ((int) ($remediated[$row->element_id] ?? 0)) / (int) $row->learners), 4),
                'recoveryRate' => $closed->count() >= $min ? round($recovered / $closed->count(), 4) : null,
                'topReasons' => $this->topReasons($s),
                // a Course Builder element of a kind "Propose an improvement" can rewrite (ADR 0181)
                'improvable' => $row->element_id && $row->session_id && in_array($this->lookup->typeOf($row->session_id, $row->element_id), InsightsProposalService::TYPES, true),
            ];
        }
        usort($rows, fn ($a, $b) => ($a['hidden'] <=> $b['hidden']) ?: match ($sort) {
            'active' => ($b['activeLearners'] ?? 0) <=> ($a['activeLearners'] ?? 0),
            'recovery' => ($a['recoveryRate'] ?? 2) <=> ($b['recoveryRate'] ?? 2),
            default => ($b['struggleRate'] ?? 0) <=> ($a['struggleRate'] ?? 0),
        });

        return ['courseId' => $courseId, 'periodDays' => $days, 'minGroupSize' => $min, 'elements' => $rows];
    }

    /**
     * Learners who are not on track in a course. Individuals: the caller checks the permission.
     *
     * @return array<int,array<string,mixed>>
     */
    public function learners(int $courseId, ?string $status = null): array
    {
        $q = DB::table('learner_statuses as s')->join('users as u', 'u.id', '=', 's.user_id')->where('s.course_id', $courseId)->where('s.element_key', '')
            ->orderByDesc('s.since')->limit(500);
        $status === null ? $q->where('s.status', '!=', 'on_track') : $q->where('s.status', $status);

        return $q->get(['s.user_id', 'u.first_name', 'u.last_name', 's.status', 's.reasons', 's.since'])->map(fn ($r) => [
            'userId' => (int) $r->user_id,
            'name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')),
            'status' => $r->status,
            'since' => CarbonImmutable::parse($r->since)->toIso8601String(),
            'reasons' => array_map(fn (array $x) => Reason::fromArray($x)->message(), (array) json_decode((string) $r->reasons, true)),
        ])->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function timeline(int $userId, int $courseId, int $limit = 300): array
    {
        return DB::table('learner_signals')->where('user_id', $userId)->where('course_id', $courseId)->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get()->map(function ($s) {
            $label = $s->element_id && $s->session_id ? $this->lookup->label($s->session_id, $s->element_id) : null;

            return ['at' => CarbonImmutable::parse($s->occurred_at)->toIso8601String(), 'type' => $s->type, 'element' => $label ?? $s->element_key, 'value' => $s->value === null ? null : (float) $s->value, 'data' => $s->data ? json_decode($s->data, true) : null, 'source' => $s->source];
        })->all();
    }

    /** @return array<string,mixed> */
    private function nudges(int $courseId, CarbonImmutable $since): array
    {
        $n = Intervention::query()->where('course_id', $courseId)->where('kind', Intervention::NUDGE)->where('created_at', '>=', $since)->get();
        $closed = $n->whereIn('outcome', [Intervention::RECOVERED, Intervention::NOT_RECOVERED]);
        $min = self::minGroup();

        return [
            'sent' => $n->where('outcome', '!=', Intervention::SUPPRESSED)->count(),
            'suppressed' => $n->where('outcome', Intervention::SUPPRESSED)->count(),
            'opened' => (int) DB::table('learner_signals')->where('course_id', $courseId)->where('type', SignalType::NUDGE_OPENED)->where('occurred_at', '>=', $since)->count(),
            'recoveryRate' => $closed->count() >= $min ? round($closed->where('outcome', Intervention::RECOVERED)->count() / $closed->count(), 4) : null,
        ];
    }

    /** @return array<string,mixed> */
    private function remediations(int $courseId, CarbonImmutable $since): array
    {
        $shown = DB::table('learner_remediation_assignments as a')->join('learner_remediations as r', 'r.id', '=', 'a.remediation_id')->where('r.course_id', $courseId)->where('a.shown_at', '>=', $since);
        $helpful = (clone $shown)->where('a.feedback', 'helpful')->count();
        $notHelpful = (clone $shown)->where('a.feedback', 'not_helpful')->count();
        $closed = Intervention::query()->where('course_id', $courseId)->where('kind', Intervention::REMEDIATION)->where('created_at', '>=', $since)->whereIn('outcome', [Intervention::RECOVERED, Intervention::NOT_RECOVERED])->get();

        return [
            'shown' => (clone $shown)->count(),
            'helpful' => $helpful,
            'notHelpful' => $notHelpful,
            'recoveryRate' => $closed->count() >= self::minGroup() ? round($closed->where('outcome', Intervention::RECOVERED)->count() / $closed->count(), 4) : null,
        ];
    }

    /** @param Collection<int,object> $transitions @return array<int,array{rule:string,count:int}> */
    private function topReasons(Collection $transitions): array
    {
        $counts = [];
        foreach ($transitions as $t) {
            foreach (array_unique(array_column((array) json_decode((string) $t->reasons, true), 'rule')) as $rule) {
                $counts[$rule] = ($counts[$rule] ?? 0) + 1;
            }
        }
        arsort($counts);

        return array_map(fn ($rule, $n) => ['rule' => $rule, 'count' => $n], array_keys(array_slice($counts, 0, 3, true)), array_values(array_slice($counts, 0, 3, true)));
    }
}
