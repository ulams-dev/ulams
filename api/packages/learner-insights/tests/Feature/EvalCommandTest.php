<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\File;
use Ulams\LearnerInsights\Eval\EvalHarness;
use Ulams\LearnerInsights\Tests\TestCase;

/** The remediation eval command on the fake driver: golden fixtures, checks and the report files (plan 7.2 of phase 5). */
class EvalCommandTest extends TestCase
{
    private function reports(): array
    {
        return File::glob(storage_path('app/evals/*-learner-insights-*.json')) ?: [];
    }

    private function clean(): void
    {
        foreach ($this->reports() as $file) {
            @unlink($file);
            @unlink(substr($file, 0, -4) . 'md');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    public function testEveryFixturePassesOnTheFakeDriverAndWritesAReport(): void
    {
        $author = $this->author();

        $this->artisan('learner-insights:eval', ['--fixtures' => 'all', '--author' => $author->getKey()])
            ->expectsOutputToContain('PASS grounding.pass_rate')
            ->expectsOutputToContain('PASS privacy.no_identity_in_requests')
            ->expectsOutputToContain('PASS injection.not_followed')
            ->assertSuccessful();

        $reports = $this->reports();
        $this->assertCount(3, $reports);
        foreach ($reports as $file) {
            $report = json_decode((string) file_get_contents($file), true);
            $this->assertTrue($report['passed'], $file . ' ' . json_encode(array_filter($report['checks'], fn ($c) => !$c['ok'])));
            $this->assertSame('fake', $report['driver']);
            $this->assertFalse($report['live']);
            foreach (['patterns.replayed', 'grounding.pass_rate', 'schema.valid', 'manifest.valid', 'citations.subset_of_provided', 'no_correct_answer_leaked', 'reading_level.not_above_lesson',
                'cost.per_remediation', 'reuse.across_learners', 'cache.read_after_first_call', 'privacy.no_identity_in_requests'] as $key) {
                $this->assertArrayHasKey($key, $report['checks']);
                $this->assertArrayHasKey('detail', $report['checks'][$key]);
            }
            $this->assertGreaterThan(0, $report['cost']['calls']);
            $this->assertArrayHasKey('usd', $report['cost']);
            $this->assertNotEmpty($report['calls']);
            $markdown = (string) file_get_contents(substr($file, 0, -4) . 'md');
            $this->assertStringContainsString('| Check | Result | Detail |', $markdown);
            $this->assertStringContainsString('## Remediations', $markdown);
            $this->assertStringContainsString('## Calls', $markdown);
        }
    }

    public function testLiveNeedsAKeyAndUnknownFixturesFail(): void
    {
        config(['ai.api_key' => null]);
        $author = $this->author();

        $this->artisan('learner-insights:eval', ['--live' => true, '--author' => $author->getKey()])->expectsOutputToContain('No API key')->assertFailed();
        $this->artisan('learner-insights:eval', ['--fixtures' => 'nope', '--author' => $author->getKey()])->expectsOutputToContain('Unknown fixture')->assertFailed();
        $this->assertSame([], $this->reports());
    }

    public function testTheReadingLevelAndLeakMeasures(): void
    {
        $easy = 'The cat sat on the mat. It was a warm day.';
        $hard = 'Notwithstanding considerable methodological heterogeneity, extraction thermodynamics determines perceived astringency.';

        $this->assertLessThan(EvalHarness::fleschKincaid($hard), EvalHarness::fleschKincaid($easy));
        $this->assertGreaterThan(EvalHarness::sentenceLength($easy), EvalHarness::sentenceLength($hard));
        $this->assertTrue(EvalHarness::gives('Grind finer so water extracts faster', 'grind finer'));
        $this->assertFalse(EvalHarness::gives('Think about surface area', 'grind finer'));
        $this->assertSame(9.0, EvalHarness::percentile([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 0.9));
    }
}
