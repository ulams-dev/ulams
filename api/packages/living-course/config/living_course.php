<?php

return [
    // Source connectors enabled on this installation (ADR 0032). Plugins register more.
    'connectors' => array_values(array_filter(array_map('trim', explode(',', (string) env('LIVING_COURSE_CONNECTORS', 'upload,git,url'))))),

    // Queue for source checks and analysis steps (defaults to the Course Builder queue: the
    // `<driver>-builder` connection, queue `builder`, retry_after above the job timeout; ADR 0083)
    'queue_connection' => env('LIVING_COURSE_QUEUE_CONNECTION', env('COURSE_BUILDER_QUEUE_CONNECTION', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? env('QUEUE_CONNECTION') . '-builder' : null)),
    'queue' => env('LIVING_COURSE_QUEUE', env('COURSE_BUILDER_QUEUE', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? 'builder' : null)),

    // Deterministic change detection (ADR 0031)
    'diff' => [
        // at most this many fragments per side; above it the check fails with a readable message
        'max_fragments' => (int) env('LIVING_COURSE_MAX_FRAGMENTS', 4000),
        // same id, different text: a "changed" pair needs at least this word-3-shingle similarity
        'same_id_similarity' => 0.5,
        // unmatched fragments become a "moved" pair at this similarity or above
        'move_similarity' => 0.6,
        // more than this share of the old tokens changed is a substantive change
        'substantive_ratio' => 0.15,
        'word_diff_bytes' => 8192,
        // words whose change flips a statement (negation, modality), per language
        'modality_words' => [
            'en' => ['not', 'no', 'never', 'must', 'should', 'may', 'deprecated', 'removed', 'default', 'required', 'optional', 'only', 'always'],
            'pl' => ['nie', 'nigdy', 'musi', 'powinien', 'moze', 'może', 'przestarzale', 'przestarzałe', 'usuniete', 'usunięte', 'domyslnie', 'domyślnie', 'wymagane', 'opcjonalne'],
            'de' => ['nicht', 'kein', 'keine', 'nie', 'muss', 'soll', 'kann', 'veraltet', 'entfernt', 'standard', 'erforderlich', 'optional'],
            'es' => ['no', 'nunca', 'debe', 'puede', 'obsoleto', 'eliminado', 'predeterminado', 'requerido', 'opcional'],
            'fr' => ['ne', 'pas', 'jamais', 'doit', 'peut', 'obsolète', 'supprimé', 'défaut', 'requis', 'optionnel'],
        ],
    ],

    // Triggers and polling
    'poll' => [
        'min_minutes' => (int) env('LIVING_COURSE_MIN_POLL_MINUTES', 60),
        'checks_per_connection_per_day' => 48,
        'failures_before_error' => 3,
    ],
    'webhook_debounce_seconds' => (int) env('LIVING_COURSE_WEBHOOK_DEBOUNCE_SECONDS', 600),

    // Cost limits (USD); every call is logged in ai_calls with the proposal as subject
    'cost' => [
        'auto_analyse_usd' => (float) env('LIVING_COURSE_AUTO_ANALYSE_USD', 0.50),
        'proposal_usd' => (float) env('LIVING_COURSE_PROPOSAL_COST_USD', 2),
        'source_monthly_usd' => (float) env('LIVING_COURSE_SOURCE_MONTHLY_USD', 10),
        'max_groups' => (int) env('LIVING_COURSE_MAX_GROUPS', 40),
        'proposals_per_source_per_day' => 5,
        'regenerations_per_item' => 3,
        'output_tokens_per_group' => 3000,
    ],

    // Learner-facing behaviour (ADR 0033)
    'learner_notice_after_days' => (int) env('LIVING_COURSE_LEARNER_NOTICE_AFTER_DAYS', 3),
    'learner_email_digest_days' => 7,

    // Git hosts and web pages (ADR 0032)
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('LIVING_COURSE_ALLOWED_HOSTS', ''))))),
    'insecure_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('LIVING_COURSE_INSECURE_HOSTS', ''))))),
    'connector_limits' => [
        'files' => 500,
        'file_bytes' => 1024 * 1024,
        'total_bytes' => 20 * 1024 * 1024,
        'page_bytes' => 5 * 1024 * 1024,
        'urls' => 20,
    ],

    // Raw revisions are kept on the Course Builder private disk under this prefix
    'revisions_prefix' => 'living-course/revisions',
];
