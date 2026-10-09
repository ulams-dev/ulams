<?php

/*
 * LLM layer (ADR 0009). Model names appear only in this file and in .env.example; a test fails
 * when a `claude-` string shows up anywhere else in a package's src/.
 */
return [
    // anthropic | fake | disabled. With no API key the anthropic driver resolves to "disabled".
    'driver' => env('AI_DRIVER', 'anthropic'),

    // The legacy spelling ANTROPHIC_API_KEY is accepted second.
    'api_key' => env('ANTHROPIC_API_KEY', env('ANTROPHIC_API_KEY')),
    'base_url' => env('ANTHROPIC_BASE_URL'),
    'timeout' => (int) env('AI_TIMEOUT', 600),
    'max_retries' => (int) env('AI_MAX_RETRIES', 2),

    'profiles' => [
        'default' => [
            'model' => env('AI_MODEL_DEFAULT', 'claude-sonnet-5-5'),
            'label' => env('AI_MODEL_DEFAULT_LABEL', 'Sonnet'),
            // Server-side refusal fallback (beta header below; Claude API only)
            'fallbacks' => env('AI_DEFAULT_FALLBACKS', 'default'),
        ],
        'light' => [
            'model' => env('AI_MODEL_LIGHT', 'claude-haiku-5-5'),
            'label' => env('AI_MODEL_LIGHT_LABEL', 'Haiku'),
            'fallbacks' => null,
        ],
        // Opt-in only (AI_TASK_<TASK>_PROFILE=premium); the default build never uses it
        'premium' => [
            'model' => env('AI_MODEL_PREMIUM', 'claude-opus-5-5'),
            'label' => env('AI_MODEL_PREMIUM_LABEL', 'Opus'),
            'fallbacks' => env('AI_PREMIUM_FALLBACKS', 'default'),
        ],
    ],

    'fallbacks_beta' => env('AI_FALLBACKS_BETA', 'server-side-fallback-2026-07-01'),

    // task → profile, effort, max output tokens, cache TTL of the source block
    'tasks' => [
        'interview' => ['profile' => env('AI_TASK_INTERVIEW_PROFILE', 'light'), 'effort' => 'low', 'max_tokens' => 4000],
        'outline' => ['profile' => env('AI_TASK_OUTLINE_PROFILE', 'default'), 'effort' => 'high', 'max_tokens' => 32000],
        'lesson' => ['profile' => env('AI_TASK_LESSON_PROFILE', 'default'), 'effort' => 'medium', 'max_tokens' => 32000],
        'quiz' => ['profile' => env('AI_TASK_QUIZ_PROFILE', 'default'), 'effort' => 'medium', 'max_tokens' => 16000],
        'metadata' => ['profile' => env('AI_TASK_METADATA_PROFILE', 'light'), 'effort' => 'low', 'max_tokens' => 4000],
        'patch' => ['profile' => env('AI_TASK_PATCH_PROFILE', 'default'), 'effort' => 'medium', 'max_tokens' => 16000],
        'grounding' => ['profile' => env('AI_TASK_GROUNDING_PROFILE', 'light'), 'effort' => 'medium', 'max_tokens' => 4000],
        // Living Course: patches for the elements affected by a source change (one call per lesson group)
        'update' => ['profile' => env('AI_TASK_UPDATE_PROFILE', 'default'), 'effort' => 'medium', 'max_tokens' => 16000],
    ],
    'task_defaults' => ['profile' => 'default', 'effort' => 'medium', 'max_tokens' => 8000],

    // Cache TTL for blocks marked cacheable: 5m or 1h (authors pause between steps)
    'cache_ttl' => env('AI_CACHE_TTL', '1h'),

    // USD per million tokens. Cache writes cost input × 1.25 (5 min) or × 2 (1 h).
    'prices' => [
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20],
        'claude-haiku-5-5' => [
            'input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01,
            'long_context' => ['above' => 100000, 'input' => 0.50, 'output' => 2.50, 'cache_read' => 0.05],
        ],
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20],
    ],
    'cache_write_multipliers' => ['5m' => 1.25, '1h' => 2.0],

    // Spend limits enforced before every call (all overridable per request subject)
    'limits' => [
        'subject_tokens' => (int) env('AI_LIMIT_SUBJECT_TOKENS', 3000000),
        'subject_cost_usd' => (float) env('AI_LIMIT_SUBJECT_COST_USD', 5),
        'tenant_monthly_usd' => (float) env('AI_LIMIT_TENANT_MONTHLY_USD', 50),
        // cache reads count at this fraction of a token against the token budget
        'cache_read_weight' => 0.1,
    ],

    'fake' => [
        // cassette: replay only (a missing cassette fails); synthetic: replay, else a registered
        // responder builds a deterministic answer (local demos and the e2e test)
        'mode' => env('AI_FAKE_MODE', 'synthetic'),
        'cassettes' => env('AI_CASSETTES_PATH'),
    ],

    // Write a cassette for every successful call (set by the eval command with --record)
    'record' => [
        'enabled' => (bool) env('AI_RECORD', false),
        'path' => env('AI_RECORD_PATH'),
    ],

    // Tests must never reach the network; the anthropic driver throws in "testing" unless true
    'allow_network_in_tests' => (bool) env('AI_ALLOW_NETWORK_IN_TESTS', false),
];
