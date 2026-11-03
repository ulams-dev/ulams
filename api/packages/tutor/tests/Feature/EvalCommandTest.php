<?php

namespace Ulams\Tutor\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Ulams\Tutor\Console\EvalCommand;
use Ulams\Tutor\Tests\TestCase;

/** The tutor eval command on the fake driver: golden questions, checks and the report files (plan 7.2 of phase 5). */
class EvalCommandTest extends TestCase
{
    private function reports(): array
    {
        return File::glob(storage_path('app/evals/*-tutor-*.json')) ?: [];
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

        $this->artisan('tutor:eval', ['--fixtures' => 'all', '--author' => $author->getKey()])
            ->expectsOutputToContain('PASS citations.precision')
            ->expectsOutputToContain('PASS leak.rate_zero')
            ->expectsOutputToContain('PASS privacy.no_identity_in_requests')
            ->assertSuccessful();

        $reports = $this->reports();
        $this->assertCount(3, $reports);
        foreach ($reports as $file) {
            $report = json_decode((string) file_get_contents($file), true);
            $this->assertTrue($report['passed'], $file . ' ' . json_encode(array_filter($report['checks'], fn ($c) => !$c['ok'])));
            $this->assertSame('fake', $report['driver']);
            $this->assertFalse($report['live']);
            foreach (['questions.asked', 'citations.precision', 'out_of_scope.declined', 'attempt.refusal_correct', 'leak.rate_zero', 'injection.not_followed',
                'latency.p95', 'latency.first_token', 'cost.per_answer', 'privacy.no_identity_in_requests'] as $key) {
                $this->assertArrayHasKey($key, $report['checks'], "{$report['fixture']} {$key}");
                $this->assertArrayHasKey('detail', $report['checks'][$key]);
            }
            foreach (['citationPrecision', 'leakRate', 'p95Seconds', 'costPerAnswerUsd', 'categories'] as $key) {
                $this->assertArrayHasKey($key, $report);
            }
            $this->assertSame(0.0, (float) $report['leakRate']);
            $this->assertNull($report['timeToFirstTokenSeconds']);
            $this->assertGreaterThan(0, $report['cost']['calls']);
            $markdown = (string) file_get_contents(substr($file, 0, -4) . 'md');
            $this->assertStringContainsString('| Check | Result | Detail |', $markdown);
            $this->assertStringContainsString('## Questions', $markdown);
            $this->assertStringContainsString('## Calls', $markdown);
        }
        $byFixture = collect($reports)->mapWithKeys(fn ($f) => [json_decode((string) file_get_contents($f), true)['fixture'] => json_decode((string) file_get_contents($f), true)]);
        $this->assertSame(['in_scope' => 20, 'out_of_scope' => 9, 'quiz_answer' => 6, 'injection_question' => 5], $byFixture['coffee']['categories']);
        $this->assertSame(40, array_sum($byFixture['git']['categories']));
    }

    public function testEachGoldenSetHasFortyQuestionsWithTheExpectedMix(): void
    {
        foreach (['coffee', 'git'] as $name) {
            $questions = json_decode((string) file_get_contents(EvalCommand::fixturePath($name)), true)['questions'];
            $this->assertCount(40, $questions);
            $this->assertSame(['in_scope' => 20, 'out_of_scope' => 9, 'quiz_answer' => 6, 'injection_question' => 5], array_count_values(array_column($questions, 'category')));
            $this->assertCount(40, array_unique(array_column($questions, 'id')));
        }
        $injection = json_decode((string) file_get_contents(EvalCommand::fixturePath('injection')), true)['questions'];
        $this->assertContains('injection_fragment', array_column($injection, 'category'));
    }

    public function testLiveNeedsAKeyAndUnknownFixturesFail(): void
    {
        config(['ai.api_key' => null]);
        $author = $this->author();

        $this->artisan('tutor:eval', ['--live' => true, '--author' => $author->getKey()])->expectsOutputToContain('No API key')->assertFailed();
        $this->artisan('tutor:eval', ['--fixtures' => 'nope', '--author' => $author->getKey()])->expectsOutputToContain('Unknown fixture')->assertFailed();
        $this->assertSame([], $this->reports());
    }
}
