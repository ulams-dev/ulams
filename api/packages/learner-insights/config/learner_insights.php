<?php

return [
    // Master switch per tenant (ADR 0061): off by default. Nothing is recorded while it is off.
    // Administrable at runtime (`learner_insights.enabled`, see the provider).
    'enabled' => (bool) env('LEARNER_INSIGHTS_ENABLED', false),

    // When true, nothing is recorded for a learner until they consented (user setting `insights.consent`).
    'require_consent' => (bool) env('LEARNER_INSIGHTS_REQUIRE_CONSENT', false),

    // Queue for signal recording (default queue when null; `insights` in the production docs).
    'queue_connection' => env('LEARNER_INSIGHTS_QUEUE_CONNECTION'),
    'queue' => env('LEARNER_INSIGHTS_QUEUE'),

    // a burst of signals of one learner in one course costs one evaluation per this many seconds
    'evaluate_debounce_seconds' => (int) env('LEARNER_INSIGHTS_EVALUATE_DEBOUNCE_SECONDS', 60),

    // Where the continue link of a nudge points (the learner frontend); falls back to the Course Builder front url
    'front_url' => env('LEARNER_INSIGHTS_FRONT_URL'),

    // Nudges (plan 7.1): in-app and e-mail through the notifications package, rate limited
    'nudges' => [
        'enabled' => (bool) env('LEARNER_INSIGHTS_NUDGES_ENABLED', true),
        // at most one nudge per learner and course in this many days
        'per_course_days' => (int) env('LEARNER_INSIGHTS_NUDGES_PER_COURSE_DAYS', 7),
        // and at most this many per learner in 7 days across courses
        'per_learner_week' => (int) env('LEARNER_INSIGHTS_NUDGES_PER_LEARNER_WEEK', 2),
    ],

    // Days an intervention may take to count as recovered
    'interventions' => ['window_days' => (int) env('LEARNER_INSIGHTS_RECOVERY_WINDOW_DAYS', 14)],

    'retention' => [
        // days a learner signal is kept; the daily `learner-insights:prune` deletes older rows
        'signals_days' => (int) env('LEARNER_INSIGHTS_RETENTION_SIGNALS_DAYS', 180),
        // status transitions, and statuses of learners no longer enrolled or who finished the course
        'history_days' => (int) env('LEARNER_INSIGHTS_RETENTION_HISTORY_DAYS', 365),
    ],

    // POST /api/learner-insights/signals: requests per minute per learner
    'client_signals_per_minute' => (int) env('LEARNER_INSIGHTS_CLIENT_SIGNALS_PER_MINUTE', 60),

    // Rules of the rule-based scorer (ADR 0058). Every value can be changed per tenant (the same keys as
    // administrable settings, `learner_insights.rules.<rule>.<param>`) and per course.
    'rules' => [
        'inactivity' => [
            'enabled' => true,
            // days without activity in the course: the learner is at risk
            'inactive_days' => 7,
            // this many struggling elements together with 3 inactive days also make the learner at risk
            'struggling_elements_at_risk' => 3,
        ],
        'failed_attempts' => [
            'enabled' => true,
            // wrong answers to the same question since the last correct one
            'failed_answers' => 2,
            // failed attempts of the same quiz since the last pass
            'failed_quiz_attempts' => 2,
        ],
        'slow_element' => [
            'enabled' => true,
            // time on an element above this multiple of the cohort median
            'slow_factor' => 3,
            // learners the median must rest on
            'min_cohort' => 10,
        ],
        'abandoned_element' => [
            'enabled' => true,
            // days since an element was opened without finishing it, while the learner was active elsewhere
            'abandoned_days' => 5,
        ],
        'repetition' => [
            'enabled' => true,
            // within 7 days: replays or seeks back in media, or revisits of a finished topic (low weight)
            'replays' => 3,
            'revisits' => 3,
        ],
    ],
];
