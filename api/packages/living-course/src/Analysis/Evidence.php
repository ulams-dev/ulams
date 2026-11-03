<?php

namespace Ulams\LivingCourse\Analysis;

use Ulams\LivingCourse\Exceptions\ProposalException;

/**
 * The learner evidence of an insights proposal (ADR 0181): aggregates only. Whatever a caller passes
 * is reduced to this shape (rates, counts, rule names, option ids with their share), so a user id, a name
 * or a row of one learner cannot reach the stored proposal, the review screen or the model, and a group
 * below the minimum size is refused here as well as where the numbers are computed.
 */
final class Evidence
{
    /**
     * @param array<string,mixed> $raw
     * @return array{periodDays:int,learners:int,minGroupSize:int,struggleRate:float,remediationRate:float,recoveryRate:?float,helpfulShare:?float,topReasons:array<int,array{rule:string,count:int}>,wrongOptions:array<int,array{optionId:string,label:string,share:float}>,tutorOutOfScope:int}
     */
    public static function sanitise(array $raw): array
    {
        $floor = max(1, (int) config('living_course.insights.min_learners', 10));
        $min = max($floor, (int) ($raw['minGroupSize'] ?? $floor));
        $learners = (int) ($raw['learners'] ?? 0);
        if ($learners < $min) {
            throw new ProposalException(sprintf('Only %d learners were active here; at least %d are needed before an improvement can be proposed from their results.', $learners, $min), 422, ['code' => 'small_group']);
        }
        $rate = fn (mixed $v): ?float => is_numeric($v) ? round(max(0.0, min(1.0, (float) $v)), 4) : null;
        $reasons = [];
        foreach (array_slice((array) ($raw['topReasons'] ?? []), 0, 5) as $r) {
            if (is_array($r) && is_string($r['rule'] ?? null) && preg_match('/^[a-z][a-z_]{0,39}$/', $r['rule'])) {
                $reasons[] = ['rule' => $r['rule'], 'count' => max(0, (int) ($r['count'] ?? 0))];
            }
        }
        $options = [];
        foreach (array_slice((array) ($raw['wrongOptions'] ?? []), 0, 6) as $o) {
            if (is_array($o) && is_string($o['optionId'] ?? null) && preg_match('/^[a-z0-9_-]{1,40}$/i', $o['optionId'])) {
                $options[] = ['optionId' => $o['optionId'], 'label' => mb_substr(trim(strip_tags((string) ($o['label'] ?? ''))), 0, 200), 'share' => $rate($o['share'] ?? 0) ?? 0.0];
            }
        }

        return [
            'periodDays' => max(1, min(365, (int) ($raw['periodDays'] ?? 30))),
            'learners' => $learners,
            'minGroupSize' => $min,
            'struggleRate' => $rate($raw['struggleRate'] ?? 0) ?? 0.0,
            'remediationRate' => $rate($raw['remediationRate'] ?? 0) ?? 0.0,
            'recoveryRate' => $rate($raw['recoveryRate'] ?? null),
            'helpfulShare' => $rate($raw['helpfulShare'] ?? null),
            'topReasons' => $reasons,
            'wrongOptions' => $options,
            'tutorOutOfScope' => max(0, (int) ($raw['tutorOutOfScope'] ?? 0)),
        ];
    }

    /** The sentence shown with the item and stored as its reason: counts and rates, no learner. */
    public static function sentence(array $e): string
    {
        $pct = fn (float $v) => (int) round($v * 100) . ' %';
        $out = sprintf('%s of %d learners struggled here in the last %d days', $pct((float) $e['struggleRate']), $e['learners'], $e['periodDays']);
        $reasons = array_map(fn ($r) => self::reasonText($r['rule']), array_slice($e['topReasons'], 0, 1));
        if ($reasons !== []) {
            $out .= '; ' . $reasons[0] . ' is the most common reason';
        }
        $wrong = $e['wrongOptions'][0] ?? null;
        if ($wrong !== null && $wrong['optionId'] !== 'other') {
            $out .= sprintf('; %s chose the same wrong answer', $pct((float) $wrong['share']));
        }

        return mb_substr($out . '.', 0, 480);
    }

    /** What a rule key means to an author. */
    public static function reasonText(string $rule): string
    {
        return match ($rule) {
            'failed_attempts' => 'failing quiz attempts',
            'slow_element' => 'spending much more time than others',
            'repetition' => 'repeating the same steps',
            'abandoned_element' => 'leaving it unfinished',
            'inactivity' => 'stopping here',
            'struggling_elements' => 'struggling with several parts at once',
            default => str_replace('_', ' ', $rule),
        };
    }

    /** Counts only, for the audit trail. @return array<string,int|float|null> */
    public static function summary(array $e): array
    {
        return ['learners' => $e['learners'], 'periodDays' => $e['periodDays'], 'struggleRate' => $e['struggleRate'], 'remediationRate' => $e['remediationRate']];
    }
}
