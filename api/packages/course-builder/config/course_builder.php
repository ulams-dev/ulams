<?php

return [
    // Private disk for uploaded sources (never served). Default: a local private root.
    'disk' => env('COURSE_BUILDER_DISK', 'course_builder_private'),
    'private_root' => env('COURSE_BUILDER_PRIVATE_ROOT', storage_path('app/private')),

    // Queue for pipeline jobs (long LLM calls)
    // On the `database` or `redis` default connection they go to `<driver>-builder` (queue `builder`,
    // retry_after above the 1800 s job timeout, see config/queue.php and ADR 0083). Any other default
    // connection (sync in tests) is used as it is.
    'queue_connection' => env('COURSE_BUILDER_QUEUE_CONNECTION', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? env('QUEUE_CONNECTION') . '-builder' : null),
    'queue' => env('COURSE_BUILDER_QUEUE', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? 'builder' : null),

    'limits' => [
        'source_bytes' => (int) env('COURSE_BUILDER_SOURCE_MB', 20) * 1024 * 1024,
        'pdf_pages' => (int) env('COURSE_BUILDER_PDF_PAGES', 300),
        'source_tokens' => (int) env('COURSE_BUILDER_SOURCE_TOKENS', 400000),
        'session_tokens' => (int) env('COURSE_BUILDER_SESSION_TOKENS', 3000000),
        'session_cost_usd' => (float) env('COURSE_BUILDER_SESSION_COST_USD', 5),
        'concurrent_runs_per_author' => (int) env('COURSE_BUILDER_CONCURRENT_RUNS', 2),
        'sessions_per_author_per_day' => (int) env('COURSE_BUILDER_SESSIONS_PER_DAY', 10),
        'lesson_concurrency' => (int) env('COURSE_BUILDER_LESSON_CONCURRENCY', 4),
        'docx_uncompressed_bytes' => (int) env('COURSE_BUILDER_DOCX_UNCOMPRESSED_MB', 100) * 1024 * 1024,
        'eval_monthly_usd' => (float) env('COURSE_BUILDER_EVAL_MONTHLY_USD', 20),
    ],

    'fragments' => ['min_tokens' => 150, 'max_tokens' => 600],

    // Pruning of the AG-UI event log
    'events_retention_days' => (int) env('COURSE_BUILDER_EVENTS_RETENTION_DAYS', 30),

    // SSE: connections close after this many seconds; the client resumes with Last-Event-ID
    'sse' => [
        'max_seconds' => (int) env('COURSE_BUILDER_SSE_SECONDS', 25),
        'poll_ms' => (int) env('COURSE_BUILDER_SSE_POLL_MS', 500),
    ],

    // Quiz answers must share words with the cited fragments (deterministic support check)
    'quiz_support_min_overlap' => 2,

    // Links shown after the apply
    'admin_url' => env('COURSE_BUILDER_ADMIN_URL', env('ADMIN_URL')),
    'front_url' => env('COURSE_BUILDER_FRONT_URL', env('FRONTEND_URL')),
];
