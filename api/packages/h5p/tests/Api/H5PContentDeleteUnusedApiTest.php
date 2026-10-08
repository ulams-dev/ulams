<?php

namespace Ulams\H5P\Tests\Api;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\H5P\Database\Factories\H5PContentFactory;
use Ulams\H5P\Database\Seeders\H5PPermissionSeeder;
use Ulams\H5P\Models\H5PContent;
use Ulams\H5P\Testing\H5PServiceFake;
use Ulams\H5P\Tests\TestCase;

class H5PContentDeleteUnusedApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(H5PPermissionSeeder::class);
    }

    public function testDeletesOnlyUnusedContentsThroughTheService(): void
    {
        H5PServiceFake::fake();
        $used = H5PContentFactory::create();
        $unused = H5PContentFactory::create();
        DB::table('topic_h5ps')->insert(['value' => $used->getKey(), 'created_at' => now(), 'updated_at' => now()]);

        $ids = $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('/api/admin/h5p/unused')
            ->assertOk()
            ->assertJsonPath('data.failed', [])
            ->json('data.ids');

        $this->assertContains($unused->getKey(), $ids);
        $this->assertNotContains($used->getKey(), $ids);
        $this->assertNull(H5PContent::query()->find($unused->getKey()));
        $this->assertNotNull(H5PContent::query()->find($used->getKey()));

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'http://h5p.test:8080/h5p/contents/' . $unused->getKey()
            && $request->hasHeader('X-Internal-Token', 'test-internal-token'));
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/h5p/contents/' . $used->getKey()));
    }

    public function testReportsFailures(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'boom'], 500)]);
        $unused = H5PContentFactory::create();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('/api/admin/h5p/unused')
            ->assertOk();

        $this->assertContains($unused->getKey(), array_column($response->json('data.failed'), 'id'));
        $this->assertNotNull(H5PContent::query()->find($unused->getKey()));
    }

    public function testRequiresDeletePermission(): void
    {
        H5PServiceFake::fake();
        $this->actingAs($this->makeStudent(), 'api')
            ->deleteJson('/api/admin/h5p/unused')
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
