<?php

/**
 * Writes the OpenAPI document without booting Laravel or touching a database.
 *
 *   php scripts/openapi.php <output.json>
 *
 * It scans the paths listed under `annotations` in config/l5-swagger.php, the same list
 * `php artisan l5-swagger:generate` uses, so both produce the same document. It needs only
 * `composer install --no-scripts` (swagger-php comes with darkaonline/l5-swagger).
 */

use OpenApi\Generator;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$out = $argv[1] ?? null;
if ($out === null) {
    fwrite(STDERR, "usage: php scripts/openapi.php <output.json>\n");
    exit(2);
}

// An application object is enough for app_path(), base_path(), storage_path() and env() in the config:
// nothing is booted, no service provider runs and no database is opened.
new Illuminate\Foundation\Application($root);

$config = require $root . '/config/l5-swagger.php';
$doc = $config['documentations'][$config['default']];
$paths = array_values(array_filter($doc['paths']['annotations'], 'is_dir'));
$exclude = $doc['paths']['excludes'] ?? [];

foreach ($doc['constants'] ?? [] as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

// the same finder l5-swagger builds, so the order of tags and operations matches `l5-swagger:generate`
$finder = new OpenApi\SourceFinder($paths, $exclude, '*.php');
$generator = new Generator();
if (!empty($doc['scanOptions']['open_api_spec_version'])) {
    $generator->setVersion($doc['scanOptions']['open_api_spec_version']);
}
$openapi = $generator->generate($finder);
file_put_contents($out, $openapi->toJson());
fwrite(STDERR, sprintf("wrote %s (%d paths)\n", $out, count((array) $openapi->paths)));
