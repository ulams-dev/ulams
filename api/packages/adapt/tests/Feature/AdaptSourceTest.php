<?php

namespace Ulams\Adapt\Tests\Feature;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Ulams\Adapt\Jobs\BuildAdaptSource;
use Ulams\Adapt\Models\AdaptSource;
use Ulams\Adapt\Services\AdaptSourceValidator;
use Ulams\Adapt\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

class AdaptSourceTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_adapt.enabled' => true, 'ulams_adapt.builder_url' => 'http://adapt-builder.test', 'ulams_adapt.builder_token' => 'secret']);
    }

    public function testTheValidatorAcceptsAValidCourseAndExplainsBrokenOnes(): void
    {
        $validator = app(AdaptSourceValidator::class);
        $this->assertSame([], $validator->validate(self::source()));

        $broken = self::source();
        $broken['blocks'][0]['_parentId'] = 'missing';
        $broken['components'][1]['_component'] = 'evil-plugin';
        $broken['components'][] = ['_id' => 'c-05', '_parentId' => 'b-05', '_component' => 'text'];
        $broken['articles'][] = ['_id' => 'a-10', '_parentId' => 'course'];
        $errors = implode("\n", $validator->validate($broken));

        $this->assertStringContainsString('/blocks/0/_parentId', $errors);
        $this->assertStringContainsString('/components/1/_component', $errors);
        $this->assertStringContainsString('duplicate id c-05', $errors);
        $this->assertStringContainsString('/articles/1/_parentId', $errors);
        $this->assertNotSame([], $validator->validate(['course' => []]));
        $this->assertNotSame([], $validator->validate('nope'));
    }

    public function testCreateVersionAndBuildIntoATrackedScormPackage(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/adapt', ['source' => self::source()])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Adapt fixture')
            ->assertJsonPath('data.current_version', 1)
            ->json('data.id');

        $v2 = self::source();
        $v2['components'][0]['body'] = 'Hello again';
        $this->actingAs($admin, 'api')->postJson("/api/admin/adapt/{$id}/versions", ['source' => $v2, 'change_note' => 'wording'])
            ->assertCreated()
            ->assertJsonPath('data.current_version', 2);
        $this->actingAs($admin, 'api')->getJson("/api/admin/adapt/{$id}/versions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.0.change_note', 'wording')
            ->assertJsonMissingPath('data.0.source');
        $this->actingAs($admin, 'api')->getJson("/api/admin/adapt/{$id}/source?version=1")->assertOk()->assertJsonPath('components.0.body', 'Hello');

        // the build worker answers with a spoor SCORM export
        $zip = $this->makeScormZip(['adapt/js/adapt.min.js' => 'window.Adapt={}', 'course/config.json' => '{}']);
        Http::fake(['adapt-builder.test/build' => Http::response(file_get_contents($zip), 200, ['Content-Type' => 'application/zip'])]);
        Queue::fake();

        $this->actingAs($admin, 'api')->postJson("/api/admin/adapt/{$id}/build")->assertStatus(202)->assertJsonPath('data.status', 'building');
        $this->actingAs($admin, 'api')->postJson("/api/admin/adapt/{$id}/build")->assertStatus(409);
        Queue::assertPushed(BuildAdaptSource::class, fn ($job) => $job->sourceId === $id && $job->version === 2);

        (new BuildAdaptSource($id, 2))->handle(app(\Ulams\Scorm\Services\Contracts\ScormServiceContract::class));

        $source = AdaptSource::query()->findOrFail($id);
        $this->assertSame('built', $source->status);
        $this->assertSame(2, $source->built_version);
        $this->assertDatabaseHas('scorm', ['id' => $source->scorm_id, 'source_format' => 'adapt']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'http://adapt-builder.test/build'
            && $r->hasHeader('X-Internal-Token', 'secret')
            && $r['source']['components'][0]['body'] === 'Hello again');
    }

    public function testAFailedBuildIsVisibleAndAHostileZipIsRejected(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/adapt', ['source' => self::source()])->json('data.id');

        $hostile = $this->makeScormZip(['../../escape.js' => 'x']);
        Http::fake(['adapt-builder.test/build' => Http::sequence()
            ->push(['error' => 'Unknown plugin'], 422)
            ->push(file_get_contents($hostile), 200)]);
        (new BuildAdaptSource($id, 1))->handle(app(\Ulams\Scorm\Services\Contracts\ScormServiceContract::class));
        $this->assertSame('failed', AdaptSource::query()->find($id)->status);
        $this->assertStringContainsString('Unknown plugin', AdaptSource::query()->find($id)->last_error);

        // the worker's output goes through the upload guard like any upload
        (new BuildAdaptSource($id, 1))->handle(app(\Ulams\Scorm\Services\Contracts\ScormServiceContract::class));
        $this->assertSame('failed', AdaptSource::query()->find($id)->status);
        $this->assertStringContainsString('leaves its folder', AdaptSource::query()->find($id)->last_error);
    }

    public function testInvalidSourcesAreRejectedWithPaths(): void
    {
        $broken = self::source();
        $broken['components'][0]['_component'] = 'evil-plugin';

        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/adapt', ['source' => $broken])
            ->assertUnprocessable()
            ->assertJsonPath('errors.source.0', fn ($e) => str_contains($e, '/components/0/_component'));
    }

    public function testTheFeatureFlagAndThePermission(): void
    {
        $this->getJson('/api/admin/adapt')->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->getJson('/api/admin/adapt')->assertForbidden();

        config(['ulams_adapt.enabled' => false]);
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/adapt')->assertNotFound();
    }
}
