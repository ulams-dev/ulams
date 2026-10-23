<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Ulams\Scorm\Tests\TestCase;

/**
 * The playback fixtures (tests/fixtures/playback, docs/plans/scorm-playback-tests.md) are the packages the
 * browser tests play; they must also pass the package parser and the upload endpoint unchanged.
 */
class ScormPlaybackFixturesTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function packages(): array
    {
        return [
            'scorm 1.2 session' => ['scorm12-session', 'scorm_12', 'SCORM 1.2 session'],
            'scorm 1.2 mastery score' => ['scorm12-mastery', 'scorm_12', 'SCORM 1.2 mastery score'],
            'scorm 2004 session' => ['scorm2004-session', 'scorm_2004', 'SCORM 2004 session'],
        ];
    }

    #[DataProvider('packages')]
    public function test_fixture_package_is_parsed(string $package, string $version, string $title): void
    {
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/parse', [
            'zip' => $this->fixture($package),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.version', $version);
        $response->assertJsonCount(1, 'data.scos');
        $response->assertJsonPath('data.scos.0.title', $title);
        $response->assertJsonPath('data.scos.0.entryUrl', 'index.html');
        $response->assertJsonPath('data.scos.0.block', false);
        $response->assertJsonPath('data.scos.0.visible', true);
    }

    public function test_mastery_score_is_read_from_the_manifest(): void
    {
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/parse', [
            'zip' => $this->fixture('scorm12-mastery'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.scos.0.scoreToPassInt', '70');
    }

    public function test_launch_data_and_completion_threshold_are_read_from_the_manifest(): void
    {
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/parse', [
            'zip' => $this->fixture('scorm2004-session'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.scos.0.launchData', 'fixture-launch-data');
        $response->assertJsonPath('data.scos.0.completionThreshold', 0.8);
    }

    #[DataProvider('packages')]
    public function test_fixture_package_is_uploaded_with_its_scripts(string $package, string $version, string $title): void
    {
        Storage::fake(config('scorm.disk'));

        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/upload', [
            'zip' => $this->fixture($package),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.scormData.version', $version);
        $response->assertJsonPath('data.scormData.scos.0.title', $title);
        $hash = $response->json('data.scormData.hashName');
        foreach (['index.html', 'runner.js', 'sco.js'] as $file) {
            Storage::disk(config('scorm.disk'))->assertExists("scorm/{$version}/{$hash}/{$file}");
        }
    }

    private function fixture(string $package): UploadedFile
    {
        $source = realpath(__DIR__ . "/../fixtures/playback/packages/{$package}.zip");
        $this->assertNotFalse($source, "fixture {$package}.zip is missing; run fixtures/playback/build.mjs");
        $target = storage_path("{$package}.zip");
        copy($source, $target);

        return new UploadedFile($target, "{$package}.zip", 'application/zip', null, true);
    }
}
