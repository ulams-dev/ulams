<?php

return [
    /*
     * Review time (ADR 0194, plan docs/plans/authoring-metric-and-report-builder.md 6.3). Off by default; a tenant
     * admin turns it on in Settings after reading what is stored. While it is off the activity endpoint answers 404
     * and nothing is collected.
     */
    'review_time' => (bool) env('AUTHORING_METRICS_REVIEW_TIME', false),

    /*
     * The academy's own earlier figure: hours of work per hour of learner time before it used the Course Builder.
     * Optional. Shown next to the measured figure and labelled as the academy's own estimate; never compared with an
     * industry figure.
     */
    'baseline_hours_per_learner_hour' => env('AUTHORING_METRICS_BASELINE_HOURS') === null ? null : (float) env('AUTHORING_METRICS_BASELINE_HOURS'),

    /* Raw activity minutes are kept this many days, then only the daily totals per course remain. */
    'raw_minutes_retention_days' => 7,
];
