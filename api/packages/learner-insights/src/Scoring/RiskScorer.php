<?php

namespace Ulams\LearnerInsights\Scoring;

/**
 * Scores one learner in one course (ADR 0058). The container binds it to RuleBasedScorer; a
 * statistical scorer can replace it later without touching consumers.
 */
interface RiskScorer
{
    public function key(): string;

    /** Bumps when the rules change; stored with every status. */
    public function version(): string;

    public function assess(LearnerCourseContext $ctx): Assessment;
}
