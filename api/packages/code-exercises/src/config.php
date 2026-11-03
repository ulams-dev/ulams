<?php

return [
    // Tenant switch: on by default. When off, launches and attempts answer 404 and the admin hides the type.
    'enabled' => filter_var(env('CODE_EXERCISES_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // The browser runner (front/code-runner, ADR 0170) is a set of static files served from the tenant
    // content origin under /runtime/code-runner/<version>/. `runtime_disk` names the local disk they
    // come from; `runtime_path` is its root (default: the package's resources/ folder, which holds
    // runtime/code-runner/<version>/). esbuild.wasm is fetched at image build (bin/fetch-esbuild.sh).
    'runner' => [
        'version' => 1,
        'files' => ['runner.html', 'runner.js', 'exec.html', 'exec.js', 'esbuild.js', 'esbuild.wasm'],
    ],
    'runtime_disk' => 'code-exercises-runtime',
    'runtime_path' => env('CODE_EXERCISES_RUNTIME_PATH'),

    // Limits for everything an author saves and a learner reports; all of it is untrusted input.
    'limits' => [
        'max_files' => 20,
        'max_test_files' => 10,
        'max_file_bytes' => 64 * 1024,
        'max_total_bytes' => 256 * 1024,
        'max_path_length' => 80,
        'max_results' => 200,
        'max_test_name' => 160,
        'max_message' => 500,
        'timeout_ms' => ['default' => 5000, 'min' => 500, 'max' => 30000],
        'attempts_per_minute' => 20,
    ],

    // Per language: the file extensions an exercise may contain and the ones a test file may have.
    'languages' => [
        'javascript' => ['extensions' => ['js', 'mjs', 'json', 'txt', 'md'], 'tests' => ['js', 'mjs']],
        'typescript' => ['extensions' => ['ts', 'js', 'mjs', 'json', 'txt', 'md'], 'tests' => ['ts', 'js', 'mjs']],
    ],

    // Never accepted as a file name, whatever the allow-list says
    'denied_names' => ['/(^|\/)\./', '/\.(php\d?|phtml|phar|sh|exe|dll|so|html?|svgz?)$/i'],
];
