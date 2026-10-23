<?php

return [
    /*
     * Review time (ADR 0194, plan docs/plans/authoring-metric-and-report-builder.md 6.3). Off by default; a tenant
     * admin turns it on in Settings after reading what is stored. While it is off the activity endpoint answers 404
     * and nothing is collected.
     */
    'review_time' => (bool) env('AUTHORING_METRICS_REVIEW_TIME', false),

    /* Raw activity minutes are kept this many days, then only the daily totals per course remain. */
    'raw_minutes_retention_days' => 7,
];
