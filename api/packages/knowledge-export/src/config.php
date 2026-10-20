<?php

/*
 * Knowledge export (Phase 7.6): course content as chunked, cited packs for RAG in company agents.
 */

return [
    // Filesystem disk for packs; null uses the default disk (R2 in production).
    'disk' => env('KNOWLEDGE_EXPORT_DISK'),
    'directory' => 'knowledge-exports',

    // Chunk size in tokens (about four characters each): sections are packed between these bounds.
    'min_tokens' => 150,
    'max_tokens' => 600,

    // Public address of the learner app, for the links in each chunk; falls back to the host of FRONTEND_URL.
    'base_url' => env('KNOWLEDGE_EXPORT_BASE_URL'),

    // Re-export a course that has been exported before when its content changes. Changes within the window collapse into one run.
    'auto' => (bool) env('KNOWLEDGE_EXPORT_AUTO', true),
    'auto_delay_seconds' => 120,

    // Exports kept per course (and for the tenant); older ones and their files are deleted.
    'keep' => 5,

    'queue' => env('KNOWLEDGE_EXPORT_QUEUE'),
    'queue_connection' => env('KNOWLEDGE_EXPORT_QUEUE_CONNECTION'),

    // Per user and minute.
    'api_rate_limit' => 60,
    'start_rate_limit' => 10,
];
