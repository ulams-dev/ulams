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

    // Streamed answers (ADR 0105): the request queues the answer, the job appends AG-UI events to `tutor_events`,
    // `GET /api/tutor/runs/{run}/events` tails them over SSE. The plain request stays the default and the fallback.
    'stream' => [
        // operator switch: off makes `stream: true` requests answer in the plain way
        'enabled' => (bool) env('TUTOR_STREAM_ENABLED', true),
        // job timeout; a run that outlives it (plus a grace period) ends with a RUN_ERROR
        'timeout_seconds' => (int) env('TUTOR_STREAM_TIMEOUT_SECONDS', 60),
        // an SSE connection closes after this many seconds and the client reconnects with Last-Event-ID
        'max_seconds' => (int) env('TUTOR_STREAM_SSE_SECONDS', 25),
        'poll_ms' => (int) env('TUTOR_STREAM_POLL_MS', 500),
        // text deltas are written at the end of a sentence or after this many milliseconds
        'coalesce_ms' => (int) env('TUTOR_STREAM_COALESCE_MS', 100),
        // queue of the answer job; null uses the default connection and queue
        'queue_connection' => env('TUTOR_STREAM_QUEUE_CONNECTION'),
        'queue' => env('TUTOR_STREAM_QUEUE'),
        // events are deleted this many days after the run ended (and with their conversation)
        'events_retention_days' => (int) env('TUTOR_STREAM_EVENTS_RETENTION_DAYS', 1),
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
