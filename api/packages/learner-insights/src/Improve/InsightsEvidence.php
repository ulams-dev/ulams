<?php

namespace Ulams\LearnerInsights\Improve;

/**
 * Aggregated evidence about one element, the only learner-derived thing an improvement proposal carries
 * (ADR 0181, ADR 0058). Rates and counts of a group of at least `minGroupSize` learners, rule names and the
 * share of that group that chose each wrong option. No user id, no name, no row of one learner, no free text.
 */
final class InsightsEvidence
{
    /**
     * @param array<int,array{rule:string,count:int}> $topReasons
     * @param array<int,array{optionId:string,label:string,share:float}> $wrongOptions shares below the minimum are merged into `other`
     */
    public function __construct(
        public readonly int $periodDays,
        public readonly int $learners,
        public readonly int $minGroupSize,
        public readonly float $struggleRate,
        public readonly float $remediationRate,
        public readonly ?float $recoveryRate,
        public readonly ?float $helpfulShare,
        public readonly array $topReasons,
        public readonly array $wrongOptions,
        public readonly int $tutorOutOfScope,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'periodDays' => $this->periodDays,
            'learners' => $this->learners,
            'minGroupSize' => $this->minGroupSize,
            'struggleRate' => $this->struggleRate,
            'remediationRate' => $this->remediationRate,
            'recoveryRate' => $this->recoveryRate,
            'helpfulShare' => $this->helpfulShare,
            'topReasons' => $this->topReasons,
            'wrongOptions' => $this->wrongOptions,
            'tutorOutOfScope' => $this->tutorOutOfScope,
        ];
    }
}
