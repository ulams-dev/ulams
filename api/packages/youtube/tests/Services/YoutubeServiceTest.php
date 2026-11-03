<?php

namespace Ulams\Youtube\Tests\Services;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Youtube\Dto\YTBroadcastDto;
use Ulams\Youtube\Events\YtProblem;
use Ulams\Youtube\Services\Contracts\AuthenticateServiceContract;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;
use Ulams\Youtube\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\Fluent\AssertableJson;

class YoutubeServiceTest extends TestCase
{
    use CreatesUsers;
    use WithFaker;

    private $user;

    public function setUp(): void
    {
        parent::setUp();

        config([
            'services.youtube.client_id' => 'test_client_id',
            'services.youtube.client_secret' => 'test_secret',
            'services.youtube.api_key' => 'test_api_key',
            'services.youtube.refresh_token' => 'test_refresh_token',
            'services.youtube.redirect_url' => 'redirect_url',
        ]);

        $this->user = $this->makeAdmin();
        $this->mock->reset();
    }

    public function testGenerateYTAuthUrl()
    {
        $googleClient = $this->mock(YoutubeServiceContract::class);
        $googleClient->shouldReceive('generateYTAuthUrl')->once()->andReturn('https://accounts.google.com/o/');

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            'api/admin/g-token/generate',
            ['email' => $this->faker->email]
        );
        $this->response->assertOk();
        $this->response->assertJson(fn (AssertableJson $json) => $json->has('url')->etc());
        $content = json_decode($this->response->content());
        $this->assertTrue((bool)preg_match('/https:\/\/accounts.google.com.*/', $content->url));
    }

    public function testGenerateYTAuthUrlIsForAdministratorsWithTheConfigPermission()
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->json('POST', 'api/admin/g-token/generate', ['email' => $this->faker->email])
            ->assertForbidden();
    }

    public function testGeneratedUrlCarriesASignedState()
    {
        // the real Google client only builds the URL, nothing leaves the process
        config(['services.youtube.redirect_url' => 'https://tenant.test/api/refresh-token']);

        $this->assertSame(3, count(explode('.', $this->startFlow())));
    }

    public function testSetRefreshToken()
    {
        $this->googleReturns(['refresh_token' => 'new_token']);

        $this->json('GET', 'api/refresh-token?' . http_build_query(['code' => 'abc', 'state' => $this->startFlow()]))
            ->assertOk();

        $this->assertSame('new_token', \Config::get('services.youtube.refresh_token'));
    }

    public function testRefreshTokenWithoutStateIsRefused()
    {
        $this->googleMustNotBeCalled();

        $this->json('GET', 'api/refresh-token?code=abc')->assertStatus(400);

        $this->assertUnchanged();
    }

    public function testRefreshTokenWithoutCodeIsRefused()
    {
        $this->googleMustNotBeCalled();

        $this->json('GET', 'api/refresh-token?' . http_build_query(['state' => $this->startFlow()]))->assertStatus(400);

        $this->assertUnchanged();
    }

    public function testRefreshTokenWithAForgedStateIsRefused()
    {
        $this->googleMustNotBeCalled();
        [$nonce, $exp] = explode('.', $this->startFlow());

        foreach ([$nonce . '.' . $exp . '.' . str_repeat('A', 43), 'garbage', $nonce . '.' . ((int) $exp + 3600) . '.' . str_repeat('A', 43)] as $state) {
            $this->json('GET', 'api/refresh-token?' . http_build_query(['code' => 'abc', 'state' => $state]))->assertStatus(400);
        }

        $this->assertUnchanged();
    }

    public function testRefreshTokenWithAStateThatWasAlreadyUsedIsRefused()
    {
        $this->googleReturns(['refresh_token' => 'first']);
        $query = http_build_query(['code' => 'abc', 'state' => $this->startFlow()]);

        $this->json('GET', 'api/refresh-token?' . $query)->assertOk();
        \Config::set('services.youtube.refresh_token', 'test_refresh_token');
        $this->json('GET', 'api/refresh-token?' . $query)->assertStatus(400);

        $this->assertUnchanged();
    }

    public function testRefreshTokenWithAnExpiredStateIsRefused()
    {
        $this->googleMustNotBeCalled();
        $state = $this->startFlow();

        $this->travel(11)->minutes();
        $this->json('GET', 'api/refresh-token?' . http_build_query(['code' => 'abc', 'state' => $state]))->assertStatus(400);

        $this->assertUnchanged();
    }

    public function testRefreshTokenWithAStateOfAnotherTenantHostIsRefused()
    {
        $this->googleMustNotBeCalled();
        $state = $this->startFlow();

        $this->json('GET', 'http://other-tenant.test/api/refresh-token?' . http_build_query(['code' => 'abc', 'state' => $state]))
            ->assertStatus(400);

        $this->assertUnchanged();
    }

    public function testRefreshTokenIsRefusedWhenTheStarterLostThePermission()
    {
        $this->googleMustNotBeCalled();
        $state = $this->startFlow();
        $this->user->syncRoles([]);
        $this->user->syncPermissions([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->json('GET', 'api/refresh-token?' . http_build_query(['code' => 'abc', 'state' => $state]))->assertStatus(400);

        $this->assertUnchanged();
    }

    public function testAGoogleFailureAnswers400()
    {
        $google = $this->mock(AuthenticateServiceContract::class);
        $google->shouldReceive('getLoginUrl')->andReturnUsing(fn ($email, $state) => 'https://accounts.google.com/o/?state=' . $state);
        $google->shouldReceive('getToken')->once()->andThrow(new \RuntimeException('invalid_grant'));
        $this->app->forgetInstance(YoutubeServiceContract::class);

        $this->json('GET', 'api/refresh-token?' . http_build_query(['code' => 'abc', 'state' => $this->startFlow()]))
            ->assertStatus(400);

        $this->assertUnchanged();
    }

    /** Starts the flow as the administrator and returns the state Google would echo back. */
    private function startFlow(): string
    {
        $url = $this->actingAs($this->user, 'api')
            ->json('POST', 'api/admin/g-token/generate', ['email' => $this->faker->email])
            ->assertOk()
            ->json('url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('state', $query);
        $this->app['auth']->forgetGuards();

        return $query['state'];
    }

    private function googleReturns(array $token): void
    {
        $google = $this->mock(AuthenticateServiceContract::class);
        $google->shouldReceive('getLoginUrl')->andReturnUsing(fn ($email, $state) => 'https://accounts.google.com/o/?state=' . $state);
        $google->shouldReceive('getToken')->once()->with('abc')->andReturn($token);
        $this->app->forgetInstance(YoutubeServiceContract::class);
    }

    private function googleMustNotBeCalled(): void
    {
        $google = $this->mock(AuthenticateServiceContract::class);
        $google->shouldReceive('getLoginUrl')->andReturnUsing(fn ($email, $state) => 'https://accounts.google.com/o/?state=' . $state);
        $google->shouldNotReceive('getToken');
        $this->app->forgetInstance(YoutubeServiceContract::class);
    }

    private function assertUnchanged(): void
    {
        $this->assertSame('test_refresh_token', \Config::get('services.youtube.refresh_token'));
    }

    public function testVerifyReportYtFail()
    {
        Event::fake();
        \Config::set('services.youtube.email', $this->faker->email);
        $ytServiceContract = app(YoutubeServiceContract::class);
        try {
            $ytServiceContract->generateYTStream(new YTBroadcastDto());
        } catch (\Exception $ex) {
        }
        Event::assertDispatched(YtProblem::class);
    }
}
