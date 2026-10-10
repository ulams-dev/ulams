<?php

/**
 * Fails when runtime code depends on something that is not installed with `composer install --no-dev`.
 *
 * Production, the MyDevil staging and the local demo stack install vendor without dev packages. Model factories
 * (database/factories) and everything in composer.json "require-dev" are therefore unavailable at runtime.
 *
 * Runtime code = api/app, api/routes, api/config, api/database/migrations and api/packages/<name>/{src,config,routes,database/migrations}.
 *
 * Usage: php scripts/check-runtime-deps.php   (exit 1 on violations)
 */

$root = getenv('RUNTIME_DEPS_ROOT') ?: dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

$runtimeDirs = array_merge(
    [$root . '/app', $root . '/routes', $root . '/config', $root . '/database/migrations'],
    glob($root . '/packages/*/src', GLOB_ONLYDIR) ?: [],
    glob($root . '/packages/*/config', GLOB_ONLYDIR) ?: [],
    glob($root . '/packages/*/routes', GLOB_ONLYDIR) ?: [],
    glob($root . '/packages/*/database/migrations', GLOB_ONLYDIR) ?: [],
);

// Namespaces of the require-dev packages, plus Faker when fakerphp/faker is not a runtime requirement.
$forbidden = [
    'Mockery\\' => 'mockery/mockery',
    'Whoops\\' => 'filp/whoops',
    'NunoMaduro\\Collision\\' => 'nunomaduro/collision',
    'Orchestra\\Testbench\\' => 'orchestra/testbench',
    'phpmock\\' => 'php-mock/php-mock-phpunit',
    'PHPUnit\\' => 'phpunit/phpunit',
    'Spatie\\LaravelIgnition\\' => 'spatie/laravel-ignition',
    'DavidBadura\\FakerMarkdownGenerator\\' => 'davidbadura/faker-markdown-generator',
];
if (!isset($composer['require']['fakerphp/faker'])) {
    $forbidden['Faker\\'] = 'fakerphp/faker';
}

$violations = [];
foreach ($runtimeDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        // Test helpers that live under src/Testing are loaded by tests only.
        if (str_contains($path, '/Testing/')) {
            continue;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $i => $line) {
            $code = ltrim($line);
            if (str_starts_with($code, '*') || str_starts_with($code, '//') || str_starts_with($code, '/*') || str_starts_with($code, '#')) {
                continue;
            }
            $where = substr($path, strlen($root) + 1) . ':' . ($i + 1);
            if (preg_match('/::factory\s*\(/', $line)) {
                $violations[] = "$where calls Model::factory() (model factories are dev-only)";
            }
            if (preg_match('/\\\\?Database\\\\Factories\\\\|\bFactory::new\s*\(/', $line) && !preg_match('/return\s+[\w\\\\]*Factory::new\(\)\s*;/', $line) && !preg_match('/^\s*use\s/', $line)) {
                $violations[] = "$where references a model factory (dev-only)";
            }
            foreach ($forbidden as $ns => $package) {
                if (preg_match('/(?<![\w\\\\])\\\\?' . preg_quote(rtrim($ns, '\\'), '/') . '(\\\\|::)/', $line)) {
                    $violations[] = "$where references {$ns}* from $package, which is not installed without dev packages";
                }
            }
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Runtime code must not depend on dev-only packages or model factories:\n");
    foreach (array_unique($violations) as $v) {
        fwrite(STDERR, "  - $v\n");
    }
    exit(1);
}
echo "Runtime code is free of dev-only dependencies.\n";
