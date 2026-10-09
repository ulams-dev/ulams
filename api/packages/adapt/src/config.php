<?php

return [
    /*
     * Adapt Path B (JSON source built by an isolated GPL-3.0 worker, ADR 0013) is off by default:
     * the worker is an extra image (Node + adapt_framework) and a build takes about 30–90 s of CPU.
     * Path A (upload an Adapt SCORM export) needs none of this.
     */
    'enabled' => filter_var(env('ADAPT_SOURCE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'builder_url' => env('ADAPT_BUILDER_URL', 'http://adapt-builder:8080'),
    'builder_token' => env('ADAPT_BUILDER_TOKEN'),
    'builder_timeout' => (int) env('ADAPT_BUILDER_TIMEOUT', 300),
    'max_source_bytes' => (int) env('ADAPT_MAX_SOURCE_KB', 4096) * 1024,

    /*
     * Components the structural validation accepts (Adapt core plugins). Plugin-specific
     * properties are validated by the worker, which carries the (GPL) plugin schemas.
     */
    'components' => [
        'accordion', 'assessmentResults', 'blank', 'gmcq', 'graphic', 'hotgraphic', 'matching',
        'mcq', 'media', 'narrative', 'slider', 'text', 'textinput',
    ],
];
