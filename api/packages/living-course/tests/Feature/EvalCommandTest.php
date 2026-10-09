<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\File;
use Ulams\LivingCourse\Tests\TestCase;

/** The eval command on the fake driver: the golden v1/v2 pairs and their checks (plan 13.3). */
class EvalCommandTest extends TestCase
{
    private function reports(): array
    {
        return File::glob(storage_path('app/evals/*-living-*.json')) ?: [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach ($this->reports() as $file) {
            @unlink($file);
            @unlink(substr($file, 0, -4) . 'md');
        }
    }

    public function testEveryFixturePassesOnTheFakeDriverAndWritesAReport(): void
    {
        $author = $this->author();

        $this->artisan('living-course:eval', ['--fixtures' => 'all', '--author' => $author->getKey()])
            ->expectsOutputToContain('PASS impact.expected_elements_decided')
            ->expectsOutputToContain('PASS injection.not_followed')
            ->assertSuccessful();

        $reports = $this->reports();
        $this->assertCount(3, $reports);
        foreach ($reports as $file) {
            $report = json_decode((string) file_get_contents($file), true);
            $this->assertTrue($report['passed'], $file . ' ' . json_encode(array_filter($report['checks'], fn ($c) => !$c['ok'])));
            $this->assertSame('fake', $report['driver']);
            $this->assertArrayHasKey('analysis.completed', $report['checks']);
            $this->assertFileExists(substr($file, 0, -4) . 'md');
            @unlink($file);
            @unlink(substr($file, 0, -4) . 'md');
        }
    }

    public function testLiveNeedsAKeyAndUnknownFixturesFail(): void
    {
        config(['ai.api_key' => null]);
        $author = $this->author();

        $this->artisan('living-course:eval', ['--live' => true, '--author' => $author->getKey()])->expectsOutputToContain('No API key')->assertFailed();
        $this->artisan('living-course:eval', ['--fixtures' => 'nope', '--author' => $author->getKey()])->expectsOutputToContain('Unknown fixture')->assertFailed();
    }
}
