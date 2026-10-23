<?php

return [
    // A grouped report on a dataset that holds learner data withholds every group with fewer distinct learners than
    // this. Unset: the Learner Insights threshold (LEARNER_INSIGHTS_MIN_GROUP_SIZE, default 10), so one number rules.
    'min_group_size' => env('REPORTING_MIN_GROUP_SIZE'),

    // Rows a preview returns (the total is always counted).
    'preview_rows' => 500,

    // Rows one export (a saved report run as CSV or JSON lines) may hold.
    'export_rows' => 1000000,

    // A grouped report with more groups than this is refused: narrow it with a filter or a coarser grouping.
    'max_groups' => 100000,
];
