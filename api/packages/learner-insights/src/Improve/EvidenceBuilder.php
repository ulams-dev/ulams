<?php

namespace Ulams\LearnerInsights\Improve;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\LearnerInsights\Services\Analytics;
use Ulams\LearnerInsights\Signals\SignalType;

/**
 * Builds the evidence of an improvement proposal from the numbers the Insights page already shows, plus
 * the distribution of wrong options. Everything is computed per group on the server: an element whose
 * active group is below `min_group_size` has no evidence at all, and a wrong option chosen by fewer
 * than `OTHER_BELOW` learners is merged into `other`.
 */
class EvidenceBuilder
{
    /** options chosen by fewer learners than this are shown as one `other` share */
    public const OTHER_BELOW = 5;

    public function __construct(private readonly Analytics $analytics)
    {
    }

    /** @return InsightsEvidence|null null when the element has no row or its group is too small */
    public function build(int $courseId, string $elementKey, int $days): ?InsightsEvidence
    {
        $row = null;
        foreach ($this->analytics->elements($courseId, $days)['elements'] as $candidate) {
            if ($candidate['elementKey'] === $elementKey) {
                $row = $candidate;
                break;
            }
        }
        if ($row === null || $row['hidden']) {
            return null;
        }
        $since = CarbonImmutable::now()->subDays($days);
        $min = Analytics::minGroup();
        $learners = (int) $row['activeLearners'];

        return new InsightsEvidence(
            $days, $learners, $min, (float) $row['struggleRate'], (float) $row['remediationRate'], $row['recoveryRate'],
            $this->helpfulShare($elementKey, $since, $min), $row['topReasons'], $this->wrongOptions($courseId, $elementKey, $since, $learners), $this->tutorOutOfScope($courseId, $since, $min),
        );
    }

    /** Share of the learners who rated the help for this element who found it helpful; null below the minimum group. */
    private function helpfulShare(string $elementKey, CarbonImmutable $since, int $min): ?float
    {
        $rated = DB::table('learner_remediation_assignments as a')->join('learner_remediations as r', 'r.id', '=', 'a.remediation_id')
            ->where('r.element_id', $elementKey)->where('a.created_at', '>=', $since)->whereIn('a.feedback', ['helpful', 'not_helpful'])
            ->selectRaw('a.feedback, COUNT(DISTINCT a.user_id) as n')->groupBy('a.feedback')->pluck('n', 'feedback');
        $total = (int) $rated->sum();

        return $total >= $min ? round(((int) ($rated['helpful'] ?? 0)) / $total, 4) : null;
    }

    /**
     * The wrong options of a quiz question and the share of the active learners who chose each, from
     * `quiz.answer` signals; options below the threshold are merged into `other`.
     *
     * @return array<int,array{optionId:string,label:string,share:float}>
     */
    private function wrongOptions(int $courseId, string $elementKey, CarbonImmutable $since, int $active): array
    {
        $options = $this->questionOptions($elementKey);
        if ($options === null || $active < 1) {
            return [];
        }
        $users = [];
        foreach (DB::table('learner_signals')->where('course_id', $courseId)->where('element_key', $elementKey)->where('type', SignalType::QUIZ_ANSWER)->where('occurred_at', '>=', $since)->get(['user_id', 'data']) as $signal) {
            $data = (array) json_decode((string) $signal->data, true);
            foreach ((array) ($data['chosen'] ?? []) as $id) {
                if (isset($options[$id]) && !$options[$id]['correct']) {
                    $users[$id][$signal->user_id] = true;
                }
            }
        }
        $out = [];
        $other = [];
        foreach ($users as $id => $chosen) {
            if (count($chosen) < self::OTHER_BELOW) {
                $other += $chosen;
                continue;
            }
            $out[] = ['optionId' => (string) $id, 'label' => mb_substr($options[$id]['text'], 0, 200), 'share' => round(min(1, count($chosen) / $active), 4)];
        }
        usort($out, fn ($a, $b) => $b['share'] <=> $a['share']);
        if (count($other) >= self::OTHER_BELOW) {
            $out[] = ['optionId' => 'other', 'label' => '', 'share' => round(min(1, count($other) / $active), 4)];
        }

        return array_slice($out, 0, 6);
    }

    /** @return array<string,array{text:string,correct:bool}>|null option id => option, for a question of the applied version */
    private function questionOptions(string $elementKey): ?array
    {
        $entry = \Ulams\CourseBuilder\Models\EntityMapEntry::query()->where('element_id', $elementKey)->where('entity_type', 'gift_question')->whereNull('retired_at')->orderBy('id')->first();
        $session = $entry ? Session::query()->find($entry->session_id) : null;
        $version = $session?->applied_version_id ? Version::query()->find($session->applied_version_id) : null;
        $found = $version ? Blueprint::find($version->document, $elementKey) : null;
        if ($found === null || $found['type'] !== 'question') {
            return null;
        }

        return collect((array) ($found['node']['options'] ?? []))->filter(fn ($o) => ($o['id'] ?? '') !== '')->mapWithKeys(fn ($o) => [(string) $o['id'] => ['text' => (string) $o['text'], 'correct' => (bool) $o['correct']]])->all();
    }

    /** Tutor questions of the course declined as out of scope, once at least `min` learners used the tutor; 0 otherwise. */
    private function tutorOutOfScope(int $courseId, CarbonImmutable $since, int $min): int
    {
        if (!Schema::hasTable('tutor_conversations') || !Schema::hasTable('tutor_messages')) {
            return 0;
        }
        $learners = (int) DB::table('tutor_conversations')->where('course_id', $courseId)->where('created_at', '>=', $since)->distinct()->count('user_id');
        if ($learners < $min) {
            return 0;
        }

        return (int) DB::table('tutor_messages as m')->join('tutor_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('c.course_id', $courseId)->where('m.role', 'tutor')->where('m.refusal', 'out_of_scope')->where('m.created_at', '>=', $since)->count();
    }
}
