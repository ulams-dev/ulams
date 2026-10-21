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

    // Queue for the jobs that call the model (remediation generation, up to 300 s): the Course Builder queue, whose
    // `retry_after` is above its job timeout (ADR 0083), like Living Course. The short jobs above stay on the default queue.
    'ai_queue_connection' => env('LEARNER_INSIGHTS_AI_QUEUE_CONNECTION', env('COURSE_BUILDER_QUEUE_CONNECTION', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? env('QUEUE_CONNECTION') . '-builder' : null)),
    'ai_queue' => env('LEARNER_INSIGHTS_AI_QUEUE', env('COURSE_BUILDER_QUEUE', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? 'builder' : null)),

    // a burst of signals of one learner in one course costs one evaluation per this many seconds
    'evaluate_debounce_seconds' => (int) env('LEARNER_INSIGHTS_EVALUATE_DEBOUNCE_SECONDS', 60),

    // Where the continue link of a nudge points (the learner frontend); falls back to the Course Builder front url
    'front_url' => env('LEARNER_INSIGHTS_FRONT_URL'),

    // Aggregates shown to authors and admins are withheld for groups smaller than this (enforced on the server)
    'min_group_size' => (int) env('LEARNER_INSIGHTS_MIN_GROUP_SIZE', 10),

    // Whether the authors of a course may see individual learners' statuses (admins with the permission always may)
    'instructors_see_individuals' => (bool) env('LEARNER_INSIGHTS_INSTRUCTORS_SEE_INDIVIDUALS', false),

    // Nudges (plan 7.1): in-app and e-mail through the notifications package, rate limited
    'nudges' => [
        'enabled' => (bool) env('LEARNER_INSIGHTS_NUDGES_ENABLED', true),
        // at most one nudge per learner and course in this many days
        'per_course_days' => (int) env('LEARNER_INSIGHTS_NUDGES_PER_COURSE_DAYS', 7),
        // and at most this many per learner in 7 days across courses
        'per_learner_week' => (int) env('LEARNER_INSIGHTS_NUDGES_PER_LEARNER_WEEK', 2),
    ],

    // Adaptive interface (ADR 0062): a presentation profile derived from statuses, overridable by the learner. Off by default.
    'adaptive_interface' => (bool) env('LEARNER_INSIGHTS_ADAPTIVE_INTERFACE', false),

    // Personal remediations (plan 7.2): grounded extra help, cached per struggle pattern, AI required
    'remediation' => [
        'enabled' => (bool) env('LEARNER_INSIGHTS_REMEDIATION_ENABLED', true),
        // Quality bar: this share of the eligible learners (a stable hash of the learner) gets no remediation, so its
        // effect can be measured against them. 0 disables the holdout; the plan proposes 20 once a tenant opts in.
        'holdout_percent' => (int) env('LEARNER_INSIGHTS_REMEDIATION_HOLDOUT_PERCENT', 0),
        // new generations per learner per day; above it the learner sees nothing new (cached help still works)
        'per_learner_day' => (int) env('LEARNER_INSIGHTS_REMEDIATION_PER_LEARNER_DAY', 3),
        // feedback on a learner's own explanation (Feynman), per learner per day
        'reflections_per_day' => (int) env('LEARNER_INSIGHTS_REFLECTIONS_PER_DAY', 5),
        // monthly AI spend of remediations, in USD, for the tenant and for one course
        'tenant_monthly_usd' => (float) env('LEARNER_INSIGHTS_MONTHLY_USD', 20),
        'course_monthly_usd' => (float) env('LEARNER_INSIGHTS_COURSE_MONTHLY_USD', 5),
        // a failed generation is not retried for this many hours
        'retry_after_hours' => 24,
    ],

    // Monthly spend of `learner-insights:eval --live`, in USD (the command refuses to start at the cap)
    'eval' => ['monthly_usd' => (float) env('LEARNER_INSIGHTS_EVAL_MONTHLY_USD', 5)],

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
