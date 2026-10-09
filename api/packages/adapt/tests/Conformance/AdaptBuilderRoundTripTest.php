<?php

namespace Ulams\Adapt\Tests\Conformance;

use Illuminate\Support\Facades\Storage;
use Ulams\Adapt\Jobs\BuildAdaptSource;
use Ulams\Adapt\Models\AdaptSource;
use Ulams\Adapt\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Scorm\Services\Contracts\ScormServiceContract;

/**
 * Path B against a real build worker (api/adapt-builder): source → framework build → SCORM import
 * (Path A) → a playable, Adapt-labelled package. Runs only when ADAPT_BUILDER_E2E_URL points to a
 * running worker (nightly conformance workflow); skipped otherwise.
 */
class AdaptBuilderRoundTripTest extends TestCase
{
    use CreatesUsers;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->url = (string) getenv('ADAPT_BUILDER_E2E_URL');
        if ($this->url === '') {
            $this->markTestSkipped('ADAPT_BUILDER_E2E_URL is not set (nightly conformance only)');
        }
        config([
            'ulams_adapt.enabled' => true,
            'ulams_adapt.builder_url' => $this->url,
            'ulams_adapt.builder_token' => (string) getenv('ADAPT_BUILDER_E2E_TOKEN'),
        ]);
    }

    public function testARealBuildIsImportedAsAnAdaptScormPackage(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $source = self::source();
        $source['components'][1] += [
            '_items' => [['text' => 'Right', '_shouldBeSelected' => true], ['text' => 'Wrong', '_shouldBeSelected' => false]],
            'body' => 'Pick one',
        ];

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/adapt', ['source' => $source])
            ->assertCreated()
            ->json('data.id');

        (new BuildAdaptSource($id, 1))->handle(app(ScormServiceContract::class));

        $built = AdaptSource::query()->findOrFail($id);
        $this->assertSame('built', $built->status, (string) $built->last_error);
        $this->assertDatabaseHas('scorm', ['id' => $built->scorm_id, 'source_format' => 'adapt', 'version' => 'scorm_12']);
        $this->assertDatabaseHas('scorm_sco', ['scorm_id' => $built->scorm_id]);
    }

    public function testTheWorkerExplainsAnInvalidCourse(): void
    {
        $admin = $this->makeAdmin();
        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/adapt', ['source' => self::source()])
            ->assertCreated()
            ->json('data.id');
        // bypass the API validation: the worker must reject a broken hierarchy on its own
        $version = AdaptSource::query()->findOrFail($id)->version(1);
        $broken = $version->source;
        $broken['blocks'][0]['_parentId'] = 'missing';
        $version->source = $broken;
        $version->save();

        (new BuildAdaptSource($id, 1))->handle(app(ScormServiceContract::class));

        $failed = AdaptSource::query()->findOrFail($id);
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('missing', (string) $failed->last_error);
    }
}
