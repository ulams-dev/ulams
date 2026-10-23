<?php

namespace Ulams\Reporting\Services;

/** The numbers that govern a report run, read from config so a tenant or an operator can change them. */
class ReportingSettings
{
    /** Distinct learners a group needs to be shown. Never below 1. */
    public function minGroupSize(): int
    {
        $configured = config('reporting.min_group_size');
        $value = $configured !== null && $configured !== '' ? (int) $configured : (int) config('learner_insights.min_group_size', 10);

        return max(1, $value);
    }

    public function previewRows(): int
    {
        return max(1, (int) config('reporting.preview_rows', 500));
    }

    public function maxGroups(): int
    {
        return max(1, (int) config('reporting.max_groups', 100000));
    }
}
