<?php

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Only the classes that build a model request may touch the AI package. The signal stream, the rules,
 * the statuses, the analytics, privacy and nudges stay on this side of the line; a new file that needs
 * the model has to be added here on purpose, which is the moment to check what it sends.
 */
class LlmBoundaryTest extends TestCase
{
    private const ALLOWED = [
        'Console/EvalCommand.php',
        'Eval/EvalHarness.php',
        'Eval/RecordingDriver.php',
        'Fake/RemediationResponders.php',
        'Http/Controllers/SupportController.php',
        'Remediation/ReflectionService.php',
        'Remediation/RemediationGenerator.php',
        'Remediation/RemediationService.php',
        'UlamsLearnerInsightsServiceProvider.php',
    ];

    /** @return string[] */
    private function filesReachingTheModel(): array
    {
        $root = realpath(__DIR__ . '/../../src');
        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && preg_match('/Ulams\\\\Ai\\\\|\bLlmClient\b|\bLlmDriver\b/', file_get_contents($file->getPathname()))) {
                $found[] = ltrim(substr($file->getPathname(), strlen($root)), '/');
            }
        }
        sort($found);

        return $found;
    }

    public function testOnlyTheRemediationCodeReachesTheModel(): void
    {
        $this->assertSame([], array_values(array_diff($this->filesReachingTheModel(), self::ALLOWED)), 'a class outside the allow-list uses the AI package: check what it sends, then add it to LlmBoundaryTest::ALLOWED');
    }

    public function testTheAllowListHasNoStaleEntries(): void
    {
        $this->assertSame([], array_values(array_diff(self::ALLOWED, $this->filesReachingTheModel())), 'remove files that no longer use the AI package from LlmBoundaryTest::ALLOWED');
    }

    public function testAnalyticsAndSignalsNeverNameAnAiClass(): void
    {
        foreach (['Services', 'Scoring', 'Signals', 'Listeners', 'Jobs', 'Models', 'Events'] as $dir) {
            foreach ($this->filesReachingTheModel() as $path) {
                $this->assertStringStartsNotWith("$dir/", $path, "$path sits in $dir, which handles learner data");
            }
        }
    }
}
