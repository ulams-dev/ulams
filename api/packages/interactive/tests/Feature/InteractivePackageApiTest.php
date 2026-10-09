<?php

namespace Ulams\Interactive\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Tests\TestCase;

class InteractivePackageApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    private function uploadFixture(string $fixture, array $manifest = [], array $extra = [], array $without = [], string $uri = '/api/admin/interactive', array $body = [])
    {
        $zip = $this->packageZip($fixture, $manifest, $extra, $without);

        return $this->actingAs($this->makeAdmin(), 'api')->post($uri, $body + ['file' => $this->upload($zip)], ['Accept' => 'application/json']);
    }

    public function testUploadCreatesAPackageWithVersionOneAndStoresTheFiles(): void
    {
        $data = $this->uploadFixture('steps')->assertCreated()
            ->assertJsonPath('data.title', 'A stepped interactive')
            ->assertJsonPath('data.current_version', 1)
            ->assertJsonPath('data.licence', 'CC-BY-4.0')
            ->assertJsonPath('data.manifest.id', 'steps')
            ->assertJsonPath('data.network', ['https://tiles.example.com'])
            ->assertJsonPath('data.network_allowed', false)
            ->json('data');

        $package = InteractivePackage::query()->findOrFail($data['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $package->storage_key);
        $disk = Storage::disk(config('filesystems.default'));
        foreach (['index.html', 'app.js', 'style.css', 'ulams-interactive.json', 'posters/intro.webp'] as $file) {
            $disk->assertExists("interactive/{$package->storage_key}/v1/{$file}");
        }
        $version = $package->version(1);
        $this->assertSame('index.html', $version->entry);
        $this->assertSame(hash('sha256', "console.log('steps');\n"), $version->files['app.js']['sha256']);
        $this->assertSame(['intro', 'middle', 'last'], $version->stepIds());
        $this->assertGreaterThan(0, $version->total_bytes);
    }

    public function testATitleOverridesTheManifestTitle(): void
    {
        $this->uploadFixture('minimal', body: ['title' => 'My title'])->assertCreated()->assertJsonPath('data.title', 'My title');
    }

    public function testVersionsAreImmutableAndTheLatestBecomesCurrent(): void
    {
        $id = $this->uploadFixture('minimal')->json('data.id');
        $key = InteractivePackage::query()->findOrFail($id)->storage_key;

        $this->uploadFixture('minimal', ['version' => '1.1.0'], ['app.js' => 'changed();'], [], "/api/admin/interactive/{$id}/versions", ['change_note' => 'tweak'])
            ->assertCreated()->assertJsonPath('data.current_version', 2);

        $disk = Storage::disk(config('filesystems.default'));
        $this->assertSame("document.title = 'minimal';\n", $disk->get("interactive/{$key}/v1/app.js"));
        $this->assertSame('changed();', $disk->get("interactive/{$key}/v2/app.js"));

        $versions = $this->actingAs($this->makeAdmin(), 'api')->getJson("/api/admin/interactive/{$id}/versions")->assertOk()->json('data');
        $this->assertSame([1, 2], array_column($versions, 'version'));
        $this->assertSame('tweak', $versions[1]['change_note']);
        $this->assertSame('1.1.0', $versions[1]['manifest_version']);
    }

    public function testANewVersionMustKeepTheManifestId(): void
    {
        $id = $this->uploadFixture('minimal')->json('data.id');

        $this->uploadFixture('minimal', ['id' => 'other-package'], [], [], "/api/admin/interactive/{$id}/versions")
            ->assertUnprocessable()->assertJsonValidationErrors(['manifest' => 'differs']);
        $this->assertSame(1, InteractivePackage::query()->findOrFail($id)->current_version);
    }

    public function testRenameListSearchAndShow(): void
    {
        $admin = $this->makeAdmin();
        $id = $this->uploadFixture('minimal')->json('data.id');
        $this->uploadFixture('steps');

        $this->actingAs($admin, 'api')->putJson("/api/admin/interactive/{$id}", ['title' => 'Renamed'])->assertOk()->assertJsonPath('data.title', 'Renamed');
        $this->actingAs($admin, 'api')->getJson('/api/admin/interactive')->assertOk()->assertJsonPath('meta.total', 2);
        $this->actingAs($admin, 'api')->getJson('/api/admin/interactive?search=renam')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
        $this->actingAs($admin, 'api')->getJson("/api/admin/interactive/{$id}")->assertOk()->assertJsonPath('data.topics_count', 0)->assertJsonPath('data.manifest.steps.0.id', 'only-step');
        $this->actingAs($admin, 'api')->getJson('/api/admin/interactive/999999')->assertNotFound();
    }

    public function testDeleteRemovesVersionsAndFilesButNotWhileTopicsUseIt(): void
    {
        $id = $this->uploadFixture('minimal')->json('data.id');
        $key = InteractivePackage::query()->findOrFail($id)->storage_key;
        $admin = $this->makeAdmin();

        $topic = new \Ulams\Interactive\Models\InteractiveTopic(['value' => $id]);
        $topic->save();
        $this->actingAs($admin, 'api')->deleteJson("/api/admin/interactive/{$id}")->assertStatus(409);
        $this->assertNotNull(InteractivePackage::query()->find($id));
        $topic->delete();

        $this->actingAs($admin, 'api')->deleteJson("/api/admin/interactive/{$id}")->assertOk();
        $this->assertNull(InteractivePackage::query()->find($id));
        $this->assertDatabaseMissing('interactive_package_versions', ['interactive_package_id' => $id]);
        $this->assertSame([], Storage::disk(config('filesystems.default'))->allFiles("interactive/{$key}"));
    }

    public function testRejectedUploadsLeaveNothingBehind(): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $cases = [
            'zip-slip' => [['../../escape.js' => 'x'], 'leaves its folder'],
            'a php file' => [['evil.php' => '<?php'], 'not allowed in a package'],
            'a dotfile' => [['.htaccess' => 'x'], 'not allowed in a package'],
            'an svgz' => [['a.svgz' => 'x'], 'not allowed in a package'],
            'an executable' => [['run.exe' => 'x'], 'not allowed in a package'],
            'an unknown type' => [['data.xyz' => 'x'], '.xyz files are not allowed'],
        ];
        foreach ($cases as $name => [$extra, $message]) {
            $this->uploadFixture('minimal', [], $extra)->assertUnprocessable()->assertJsonValidationErrors(['file' => $message]);
            $this->assertSame(0, InteractivePackage::query()->count(), $name);
        }
        $this->assertSame([], $disk->allFiles('interactive'));
    }

    public function testASymlinkInTheArchiveIsRejected(): void
    {
        $zip = $this->makeZip(['index.html' => '<p>x</p>'], ['link.html' => '/etc/passwd']);
        $this->actingAs($this->makeAdmin(), 'api')->post('/api/admin/interactive', ['file' => $this->upload($zip)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'symbolic link']);
    }

    public function testAnArchiveWithoutAManifestOrNotAZipIsRejected(): void
    {
        $this->uploadFixture('minimal', [], [], ['ulams-interactive.json'])->assertUnprocessable()->assertJsonValidationErrors(['manifest' => 'needs ulams-interactive.json']);

        $notZip = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($notZip, 'plain text');
        $this->actingAs($this->makeAdmin(), 'api')->post('/api/admin/interactive', ['file' => $this->upload($notZip)], ['Accept' => 'application/json'])->assertUnprocessable();
        @unlink($notZip);
        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/admin/interactive', [])->assertUnprocessable();
    }

    public function testTheSizeLimitComesFromTheUploadsPolicy(): void
    {
        config(['ulams_uploads.policies.interactive.max_size' => 200]);
        $this->uploadFixture('steps')->assertUnprocessable()->assertJsonValidationErrors(['file' => 'limit']);
    }

    public function testAnOversizedManifestIsRejected(): void
    {
        config(['ulams_interactive.max_manifest_bytes' => 300]);
        $this->uploadFixture('steps')->assertUnprocessable()->assertJsonValidationErrors(['manifest' => 'too large']);
    }

    public function testEveryEndpointNeedsTheInteractiveManagePermission(): void
    {
        $id = $this->uploadFixture('minimal')->json('data.id');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/admin/interactive')->assertUnauthorized();
        $student = $this->makeStudent();
        foreach ([
            ['GET', '/api/admin/interactive'],
            ['POST', '/api/admin/interactive'],
            ['GET', "/api/admin/interactive/{$id}"],
            ['PUT', "/api/admin/interactive/{$id}"],
            ['DELETE', "/api/admin/interactive/{$id}"],
            ['GET', "/api/admin/interactive/{$id}/versions"],
            ['POST', "/api/admin/interactive/{$id}/versions"],
            ['GET', "/api/admin/interactive/{$id}/preview"],
        ] as [$method, $uri]) {
            $this->actingAs($student, 'api')->json($method, $uri, ['title' => 'x'])->assertForbidden();
        }
        $this->assertNotNull(InteractivePackage::query()->find($id));
    }

    public function testThePreviewReturnsTheEntryOnTheContentOrigin(): void
    {
        $admin = $this->makeAdmin();
        $id = $this->uploadFixture('minimal')->json('data.id');
        $key = InteractivePackage::query()->findOrFail($id)->storage_key;

        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);
        $this->actingAs($admin, 'api')->getJson("/api/admin/interactive/{$id}/preview")->assertStatus(503);

        config(['ulams_uploads.content_origin' => 'http://coffee.content.localhost']);
        $data = $this->actingAs($admin, 'api')->getJson("/api/admin/interactive/{$id}/preview")->assertOk()->json('data');
        $this->assertSame("http://coffee.content.localhost/interactive/{$key}/v1/index.html", $data['url']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $data['nonce']);
        $this->actingAs($admin, 'api')->getJson("/api/admin/interactive/{$id}/preview?version=9")->assertNotFound();
    }
}
