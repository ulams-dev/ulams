<?php

namespace Ulams\Auth\Console\Commands;

use Illuminate\Console\Command;
use Ulams\Auth\Support\TokenScopes;

class ExportTokenScopesCommand extends Command
{
    protected $signature = 'ulams:tokens:export-scopes {--check : Fail when the committed JSON is out of date}';

    protected $description = 'Write the token scope vocabulary and route map as JSON for the CLI generator';

    public function handle(): int
    {
        $json = json_encode([
            'contract' => 1,
            'areas' => TokenScopes::AREAS,
            'scopes' => TokenScopes::all(),
            'presets' => TokenScopes::PRESETS,
            'routes' => array_map(fn ($e) => ['pattern' => $e[0], 'area' => $e[1]] + (isset($e[2]) ? ['options' => $e[2]] : []), TokenScopes::map()),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $path = __DIR__ . '/../../../resources/token-scopes.json';
        if ($this->option('check')) {
            if (!is_file($path) || file_get_contents($path) !== $json) {
                $this->error('token-scopes.json is out of date; run php artisan ulams:tokens:export-scopes');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }
        file_put_contents($path, $json);
        $this->info('Wrote ' . realpath($path));

        return self::SUCCESS;
    }
}
