<?php

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** The package reads LMS data but changes it only through domain services (CLAUDE.md non-negotiables). */
class GuardsTest extends TestCase
{
    /** @return array<string,string> path => code */
    private function sources(): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src'));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    public function testNoDirectWritesToTablesOfOtherPackages(): void
    {
        foreach ($this->sources() as $path => $code) {
            $this->assertDoesNotMatchRegularExpression(
                '/DB::table\(\'(?!learner_)[a-z_]+\'\)[^;]*->(insert|insertOrIgnore|update|upsert|delete|truncate)\b/s',
                $code,
                basename($path) . ' writes to a table it does not own',
            );
        }
    }

    public function testNoModelNamesOutsideConfig(): void
    {
        foreach ($this->sources() as $path => $code) {
            $this->assertDoesNotMatchRegularExpression('/claude-|gpt-|gemini-|sonnet|opus|haiku/i', $code, basename($path) . ' names a model');
        }
    }

    public function testInternalEventsCarryNoUserObject(): void
    {
        foreach ($this->sources() as $path => $code) {
            // LearnerNudged is the one event that notifies on purpose (plan 7.1); the others must stay silent
            if (!str_contains($path, '/Events/') || basename($path) === 'LearnerNudged.php') {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/\b(User|Authenticatable)\s+\$/', $code, basename($path) . ' carries a user object');
        }
        $this->assertTrue(true);
    }
}
