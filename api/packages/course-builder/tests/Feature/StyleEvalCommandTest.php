<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Illuminate\Support\Facades\File;
use Ulams\CourseBuilder\Console\StyleEvalCommand;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\Tests\TestCase;

/** The style eval on the fake driver: extraction recall per rule, citation coverage, adherence of a generated course, and no trace left. */
class StyleEvalCommandTest extends TestCase
{
    private function reports(): array
    {
        return File::glob(storage_path('app/evals/*-style.json')) ?: [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach ($this->reports() as $file) {
            @unlink($file);
            @unlink(substr($file, 0, -4) . 'md');
        }
    }

    public function testItPassesOnTheFakeDriverWritesAReportAndLeavesTheAcademyDefaultAlone(): void
    {
        $author = $this->admin();
        // an approved academy default that the eval must not replace or remove
        $default = $this->actingAs($author, 'api')->postJson('/api/admin/course-builder/style-profiles', ['name' => 'Academy', 'profile' => ['voice' => ['person' => 'we', 'citations' => []]]])->assertCreated()->json('data.id');
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/style-profiles/{$default}/approve")->assertOk();

        $this->artisan('course-builder:eval-style', ['--author' => $author->getKey()])
            ->expectsOutputToContain('PASS extract.recall.banned')
            ->expectsOutputToContain('PASS extract.citation_coverage')
            ->expectsOutputToContain('PASS adherence.banned')
            ->assertSuccessful();

        $reports = $this->reports();
        $this->assertCount(1, $reports);
        $report = json_decode((string) file_get_contents($reports[0]), true);
        $this->assertTrue($report['passed'], json_encode(array_filter($report['checks'], fn ($c) => !$c['ok'])));
        $this->assertSame('fake', $report['driver']);
        $this->assertEquals(1.0, $report['rates']['citation_coverage']);
        $this->assertEquals(1.0, $report['rates']['recall.terminology']);
        $this->assertGreaterThan(0, $report['cost']['calls']);
        $this->assertArrayHasKey('extractionMicroUsd', $report['cost']);
        $this->assertFileExists(substr($reports[0], 0, -4) . 'md');

        $this->assertSame($default, StyleProfile::current()?->id, 'the academy default is untouched');
        $this->assertSame(1, StyleProfile::query()->count(), 'the eval profile is gone');
        $this->assertSame(0, Session::query()->where('status', Session::STYLE_GUIDE)->count());
    }

    public function testRecallScoresARuleByTheShareOfTheGuidesValuesItFinds(): void
    {
        $expected = json_decode((string) file_get_contents(__DIR__ . '/../../resources/fixtures/style-guide.expected.json'), true);
        $found = StyleEvalCommand::recall($expected, [
            'voice' => ['person' => 'you', 'formality' => 4],
            'banned' => ['phrases' => [['phrase' => 'simply'], ['phrase' => 'just']]],
            'terminology' => [['use' => 'learner']],
            'readingLevel' => [['language' => 'en', 'level' => 'grade-8']],
        ]);
        $this->assertSame([1, 3], $found['voice']);
        $this->assertSame([2, 3], $found['banned']);
        $this->assertSame([1, 3], $found['terminology']);
        $this->assertSame([1, 2], $found['readingLevel']);
        $this->assertSame([0, 3], $found['components']);
        $this->assertSame([0, 2], $found['examples']);
    }

    public function testLiveNeedsAKey(): void
    {
        config(['ai.api_key' => null]);
        $this->artisan('course-builder:eval-style', ['--live' => true, '--author' => $this->admin()->getKey()])->expectsOutputToContain('No API key')->assertFailed();
    }
}
