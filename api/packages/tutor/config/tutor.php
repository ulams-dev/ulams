<?php

return [
    // Off by default per tenant (ADR 0060, 0061): the tutor answers only from the course, with citations.
    'enabled' => (bool) env('TUTOR_ENABLED', false),

    // Profile of the `tutor` task is set in the ai package (AI_TASK_TUTOR_PROFILE, default `light`)

    'limits' => [
        // questions per learner per day
        'daily_questions' => (int) env('TUTOR_DAILY_QUESTIONS', 30),
        // requests per minute per learner
        'per_minute' => (int) env('TUTOR_PER_MINUTE', 6),
        // monthly AI spend of the tutor for the tenant, in USD
        'tenant_monthly_usd' => (float) env('TUTOR_TENANT_MONTHLY_USD', 30),
        'question_chars' => 1000,
        // characters of course content in one request (about 6000 tokens)
        'context_chars' => 24000,
    ],

    'retrieval' => [
        // chunks matching the question across the course, plus up to `topic_chunks` of the open topic
        'top' => 8,
        'topic_chunks' => 4,
        'chunk_chars' => 1200,
    ],

    // conversations and messages are deleted after this many days (`tutor:prune`, daily)
    'retention_days' => (int) env('TUTOR_RETENTION_DAYS', 90),

    // text-search configuration per course language (PostgreSQL has none for Polish: `simple`)
    'search_configs' => [
        'en' => 'english', 'de' => 'german', 'es' => 'spanish', 'fr' => 'french', 'pt' => 'portuguese', 'it' => 'italian', 'nl' => 'dutch',
    ],
];
