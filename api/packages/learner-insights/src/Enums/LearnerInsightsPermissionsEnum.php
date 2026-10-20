<?php

namespace Ulams\LearnerInsights\Enums;

final class LearnerInsightsPermissionsEnum
{
    /** Aggregates per course and element (groups of at least `min_group_size`). Seeded for admin. */
    public const LEARNER_INSIGHTS_VIEW = 'learner_insights_view';

    /** Individual learner statuses and timelines. Seeded for admin; never for tutors by default (ADR 0061). */
    public const LEARNER_INSIGHTS_VIEW_INDIVIDUALS = 'learner_insights_view_individuals';

    /** Thresholds, switches, backfill. Seeded for admin. */
    public const LEARNER_INSIGHTS_MANAGE = 'learner_insights_manage';

    /** @return string[] */
    public static function all(): array
    {
        return [self::LEARNER_INSIGHTS_VIEW, self::LEARNER_INSIGHTS_VIEW_INDIVIDUALS, self::LEARNER_INSIGHTS_MANAGE];
    }
}
