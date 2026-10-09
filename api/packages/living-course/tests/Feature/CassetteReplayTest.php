<?php

namespace Ulams\LivingCourse\Tests\Feature;

use Illuminate\Support\Facades\File;
use Ulams\Ai\Models\AiCall;
use Ulams\LivingCourse\Console\EvalCommand;
use Ulams\LivingCourse\Tests\TestCase;

/**
 * The eval ran once against the real model with --record; these tests replay the recorded answers on
 * the fake driver, so CI exercises exactly what the model said, with no network and no cost.
 * Ids are normalised in the cassettes, so they replay against any database.
 */
class CassetteReplayTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        // the recorded update and grounding answers; the course itself is built by the synthetic stand-ins
        $app['config']->set('ai.fake.cassettes', EvalCommand::cassettePath());
        $app['config']->set('ai.fake.mode', 'synthetic');
    }

    public function testRecordedCassettesExistForEveryFixture(): void
    {
        $files = File::allFiles(EvalCommand::cassettePath() . '/update/v1');

        $this->assertGreaterThanOrEqual(12, count($files), 'coffee 4 + git 3 + injection 3 groups, at least');
        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file->getPathname()), true);
            $this->assertNotEmpty($data['text']);
            $this->assertStringNotContainsString('sk-ant', $file->getContents(), 'a key must never reach a cassette');
        }
    }

    public function testTheRecordedAnswersReplayThroughTheWholeAnalysisAndPassTheChecks(): void
    {
        $author = $this->author();

        $this->artisan('living-course:eval', ['--fixtures' => 'all', '--author' => $author->getKey()])
            ->expectsOutputToContain('PASS answers.change_detected')
            ->assertSuccessful();

        $replayed = AiCall::query()->where('task', 'update')->where('request_id', 'like', 'cassette:%')->count();
        $this->assertGreaterThanOrEqual(10, $replayed, 'the update calls were answered from cassettes, not by the synthetic stand-in');
        $this->assertSame(0, AiCall::query()->where('task', 'update')->where('request_id', 'like', 'synthetic%')->count());
        foreach (File::glob(storage_path('app/evals/*-living-*')) ?: [] as $report) {
            @unlink($report);
        }
    }
}
