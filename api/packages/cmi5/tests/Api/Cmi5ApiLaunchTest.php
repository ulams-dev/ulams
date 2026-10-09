<?php

namespace Ulams\Cmi5\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Ulams\Cmi5\Database\Seeders\Cmi5PermissionSeeder;
use Ulams\Cmi5\Enums\Cmi5PermissionEnum;
use Ulams\Cmi5\Models\Cmi5;
use Ulams\Cmi5\Tests\TestCase;
use Ulams\Cmi5\Tests\Traits\Cmi5Testing;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Lrs\Database\Seeders\LrsSeeder;
use Ulams\Lrs\Models\LaunchToken;

/**
 * Learners launch cmi5 AUs from the tenant content origin with a one-time token (ADR 0046).
 */
class Cmi5ApiLaunchTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, Cmi5Testing;

    private int $auId;
    private string $entry;
    private int $cmi5Id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Cmi5PermissionSeeder::class);
        $this->seed(LrsSeeder::class);
        Storage::fake();

        $data = $this->uploadCmi5('cmi5.zip');
        $this->cmi5Id = $data->id;
        $this->auId = $data->au[0]->id;
        $this->entry = $data->au[0]->url;
        $this->app['auth']->forgetGuards(); // the upload helper acted as an admin
    }

    public function testStudentsHaveTheReadPermissionButNotDelete(): void
    {
        $student = $this->makeStudent();

        $this->assertTrue($student->can(Cmi5PermissionEnum::CMI5_READ));
        $this->assertFalse($student->can(Cmi5PermissionEnum::CMI5_DELETE));
        $this->assertFalse($student->can(Cmi5PermissionEnum::CMI5_UPLOAD));
        $this->assertFalse($student->can(Cmi5PermissionEnum::CMI5_LIST));
        $this->assertTrue($this->makeAdmin()->can(Cmi5PermissionEnum::CMI5_DELETE));
    }

    public function testAStudentCanLaunchAnAu(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->json('GET', '/api/cmi5/player/' . $this->auId)
            ->assertOk()
            ->assertViewIs('cmi5::player');
    }

    public function testAStudentCannotDeleteAPackage(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->json('DELETE', '/api/admin/cmi5/' . $this->cmi5Id)
            ->assertForbidden();

        $this->assertDatabaseHas('cmi5s', ['id' => $this->cmi5Id]);
    }

    public function testDeletingNeedsTheDeletePermissionNotTheReadPermission(): void
    {
        // read, list and upload without delete: refused
        $user = $this->makeStudent();
        $user->givePermissionTo([Cmi5PermissionEnum::CMI5_READ, Cmi5PermissionEnum::CMI5_LIST, Cmi5PermissionEnum::CMI5_UPLOAD]);

        $this->actingAs($user, 'api')->json('DELETE', '/api/admin/cmi5/' . $this->cmi5Id)->assertForbidden();

        $user->givePermissionTo(Cmi5PermissionEnum::CMI5_DELETE);
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'api')->json('DELETE', '/api/admin/cmi5/' . $this->cmi5Id)->assertOk();
    }

    public function testTheAuUrlIsOnTheContentOrigin(): void
    {
        config(['ulams_uploads.content_origin' => 'http://tea.content.localhost/']);

        $url = $this->launchUrl();

        $this->assertStringStartsWith("http://tea.content.localhost/cmi5/{$this->cmi5Id}/{$this->entry}?", $url);
    }

    public function testWithoutAContentOriginTheUrlComesFromTheDisk(): void
    {
        config(['ulams_uploads.content_origin' => null, 'scorm.content_origin' => null]);

        $url = $this->launchUrl();

        $this->assertStringContainsString("cmi5/{$this->cmi5Id}/{$this->entry}", $url);
        $this->assertStringNotContainsString('content.localhost', $url);
    }

    public function testTheLaunchUrlCarriesOnlyAOneTimeToken(): void
    {
        config(['ulams_uploads.content_origin' => 'http://tea.content.localhost']);
        $student = $this->makeStudent();
        $passport = $student->createToken('learner')->accessToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$passport}"])
            ->json('GET', '/api/cmi5/player/' . $this->auId . '?format=json')
            ->assertOk()
            ->assertJsonPath('data.origin', 'http://tea.content.localhost');
        $url = $response->json('data.url');

        $this->assertStringNotContainsString($passport, urldecode($url));
        $this->assertStringNotContainsString('eyJ', urldecode($url));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        parse_str((string) parse_url($query['fetch'], PHP_URL_QUERY), $fetch);
        $this->assertSame(['token'], array_keys($fetch), 'the fetch URL has no parameter besides the one-time token');
        $this->assertDatabaseHas('lrs_launch_tokens', [
            'token_hash' => LaunchToken::hash($fetch['token']),
            'user_id' => $student->getKey(),
            'au_id' => $this->auId,
            'registration' => $query['registration'],
        ]);
    }

    public function testTheLaunchTokenSerialisesIntoAnLrsOnlySession(): void
    {
        $url = $this->launchUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $token = $this->postJson($query['fetch'])->assertOk()->json('auth-token');

        $this->assertStringStartsWith('ulrs1.', $token);
        // the learner's identity is gone from the session; the Passport guard does not know it
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/cmi5/player/' . $this->auId)->assertUnauthorized();
    }

    public function testTenantIsolationLaunchUsesTheTenantsOwnOriginsAndTokensDoNotCrossTenants(): void
    {
        config(['ulams_uploads.content_origin' => 'http://tea.content.localhost']);

        $url = $this->actingAs($this->makeStudent(), 'api')
            ->json('GET', 'http://tea.localhost/api/cmi5/player/' . $this->auId . '?format=json')
            ->assertOk()
            ->json('data.url');

        $this->assertStringStartsWith('http://tea.content.localhost/', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('http://tea.localhost/api/cmi5/fetch?token=', $query['fetch']);
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/') . '/trax/api/', $query['endpoint']); // APP_URL is per tenant

        // another tenant has its own database and APP_KEY: the session token it gets from a
        // launch of this tenant is not valid there
        $token = $this->postJson($query['fetch'])->assertOk()->json('auth-token');
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Basic ' . $token, 'X-Experience-API-Version' => '1.0.3'])
            ->getJson(parse_url($query['endpoint'], PHP_URL_PATH) . '/statements')
            ->assertUnauthorized();
    }

    public function testAnUnknownAuAndGuestsAreRefused(): void
    {
        $this->actingAs($this->makeStudent(), 'api')->json('GET', '/api/cmi5/player/9999')->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->json('GET', '/api/cmi5/player/' . $this->auId)->assertUnauthorized();
    }

    public function testMoveToBucketCopiesPackagesOnceAndKeepsTheSource(): void
    {
        $source = Storage::fake('legacy');
        $target = Storage::fake('bucket');
        config(['ulams_cmi5.disk' => 'bucket']);
        $source->put("cmi5/{$this->cmi5Id}/index.html", '<html></html>');
        $source->put("cmi5/{$this->cmi5Id}/js/app.js", 'x');

        $this->artisan('cmi5:move-to-bucket', ['--from' => 'legacy'])
            ->expectsOutputToContain('Copied 2 file(s)')
            ->assertSuccessful();

        $target->assertExists("cmi5/{$this->cmi5Id}/index.html");
        $target->assertExists("cmi5/{$this->cmi5Id}/js/app.js");
        $source->assertExists("cmi5/{$this->cmi5Id}/index.html");

        $this->artisan('cmi5:move-to-bucket', ['--from' => 'legacy'])
            ->expectsOutputToContain('Copied 0 file(s) from [legacy] to [bucket], skipped 2')
            ->assertSuccessful();
    }

    private function launchUrl(): string
    {
        return $this->actingAs($this->makeStudent(), 'api')
            ->json('GET', '/api/cmi5/player/' . $this->auId . '?format=json')
            ->assertOk()
            ->json('data.url');
    }
}
