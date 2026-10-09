<?php

namespace Ulams\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * No model names outside config: fails on a `claude-` string in any package's src/ or prompts.
 * Models live in packages/ai/config/ai.php and .env.example only.
 */
class ModelNamesGuardTest extends TestCase
{
    public function testNoModelNamesInPackageSources(): void
    {
        $packages = dirname(__DIR__, 3);
        $offenders = [];
        $dirs = array_merge(glob($packages . '/*/src', GLOB_ONLYDIR) ?: [], glob($packages . '/*/resources/prompts', GLOB_ONLYDIR) ?: []);
        foreach ($dirs as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!$file->isFile() || !preg_match('/\.(php|md|json|ya?ml)$/', $file->getFilename())) {
                    continue;
                }
                if (preg_match('/claude-(?:opus|sonnet|haiku|fable|mythos|\d)/i', (string) file_get_contents($file->getPathname()))) {
                    $offenders[] = substr($file->getPathname(), strlen($packages) + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Model names belong in packages/ai/config/ai.php only');
    }
}
