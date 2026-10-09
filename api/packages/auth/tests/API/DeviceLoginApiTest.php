<?php

namespace Ulams\Auth\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\DeviceAuthorization;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

/** Device login (ADR 0075, docs/plans/cli.md 5.3): our own RFC 8628 flow approved in the web app. */
class DeviceLoginApiTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['app.frontend_url' => 'https://coffee.app.example']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function start(array $scopes = ['courses:write', 'builder:write'], array $extra = []): array
    {
        return $this->postJson('/api/auth/device/code', $extra + ['client_name' => 'ulams-cli on mateusz-mbp', 'scopes' => $scopes])->assertOk()->json();
    }

    private function poll(string $deviceCode)
    {
        return $this->postJson('/api/auth/device/token', ['device_code' => $deviceCode]);
    }

    private function later(int $seconds = 6): void
    {
        Carbon::setTestNow(now()->addSeconds($seconds));
    }

    public function testCodeResponseFollowsRfc8628(): void
    {
        $res = $this->start();
        $this->assertMatchesRegularExpression('/^[BCDFGHJKLMNPQRSTVWXZ]{4}-[BCDFGHJKLMNPQRSTVWXZ]{4}$/', $res['user_code']);
        $this->assertSame(600, $res['expires_in']);
        $this->assertSame(5, $res['interval']);
        $this->assertSame('https://coffee.app.example/cli/authorize', $res['verification_uri']);
        $this->assertSame('https://coffee.app.example/cli/authorize?code=' . $res['user_code'], $res['verification_uri_complete']);
        $this->assertSame(64, strlen($res['device_code']));
        $this->assertSame(['device_code', 'expires_in', 'interval', 'user_code', 'verification_uri', 'verification_uri_complete'], collect(array_keys($res))->sort()->values()->all());
    }

    public function testCodesAreStoredOnlyAsKeyedHashes(): void
    {
        $res = $this->start();
        $dump = json_encode(DB::table('device_authorizations')->get());
        $this->assertStringNotContainsString($res['device_code'], $dump);
        $this->assertStringNotContainsString(str_replace('-', '', $res['user_code']), $dump);
        $row = DeviceAuthorization::query()->firstOrFail();
        $this->assertSame(hash_hmac('sha256', $res['device_code'], config('app.key')), $row->device_code_hash);
        $this->assertSame('pending', $row->status);
        $this->assertSame(['builder:write', 'courses:write'], $row->requested_scopes);
        $this->assertEqualsWithDelta(600, now()->diffInSeconds($row->expires_at, false), 2);
    }

    public function testCodeValidation(): void
    {
        $this->postJson('/api/auth/device/code', ['scopes' => ['courses:read']])->assertStatus(422);
        $this->postJson('/api/auth/device/code', ['client_name' => 'x', 'scopes' => []])->assertStatus(422);
        $this->postJson('/api/auth/device/code', ['client_name' => 'x', 'scopes' => ['nope:read']])->assertStatus(422);
        $this->postJson('/api/auth/device/code', ['client_name' => 'x', 'scopes' => ['platform:read']])->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
        $this->postJson('/api/auth/device/code', ['client_name' => '<b>x</b>', 'scopes' => ['@author'], 'agent' => 'claude-code'])->assertOk();
        $row = DeviceAuthorization::query()->firstOrFail();
        $this->assertSame('x', $row->client_name);
        $this->assertSame('claude-code', $row->agent_name);
    }

    public function testCodeEndpointIsThrottledPerIp(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/device/code', ['client_name' => 'x', 'scopes' => ['courses:read']])->assertOk();
        }
        $this->postJson('/api/auth/device/code', ['client_name' => 'x', 'scopes' => ['courses:read']])->assertStatus(429);
    }

    public function testPollingDoesNotUseUpTheCodeBudget(): void
    {
        $res = $this->start();
        for ($i = 0; $i < 30; $i++) {
            $this->poll($res['device_code'])->assertStatus(400);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/auth/device/code', ['client_name' => 'x', 'scopes' => ['courses:read']])->assertOk();
        }
    }

    public function testPollingErrorsAreRfcErrorsWithHttp400(): void
    {
        $res = $this->start();
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'authorization_pending');
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'slow_down');
        $this->later();
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'authorization_pending');
        $this->poll(str_repeat('a', 64))->assertStatus(400)->assertJsonPath('error', 'expired_token');
        $this->postJson('/api/auth/device/token', [])->assertStatus(422);
        $this->postJson('/api/auth/device/token', ['device_code' => 'x', 'grant_type' => 'password'])->assertStatus(422);
    }

    public function testFullFlowApproveThenOneShotToken(): void
    {
        $user = $this->makeInstructor();
        $res = $this->start(['courses:write', 'builder:write', 'reports:read']);
        $code = $res['user_code'];

        $this->actingAs($user, 'api')->getJson("/api/auth/device/requests/{$code}")->assertOk()
            ->assertJsonPath('data.client_name', 'ulams-cli on mateusz-mbp')
            ->assertJsonPath('data.requested_scopes', ['builder:write', 'courses:write', 'reports:read'])
            ->assertJsonPath('data.expiry_options_days', [7, 30, 90])->assertJsonPath('data.default_expires_in_days', 90)
            ->assertJsonPath('data.max_expires_in_days', 365)->assertJsonPath('data.user_code', $code);
        $this->assertNotNull($this->actingAs($user, 'api')->getJson("/api/auth/device/requests/{$code}")->json('data.ip'));

        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'authorization_pending');

        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$code}/approve", ['scopes' => ['courses:write', 'reports:read'], 'expires_in_days' => 30])
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.scopes', ['courses:write', 'reports:read']);

        $this->later();
        $token = $this->poll($res['device_code'])->assertOk()->json();
        $this->assertStringStartsWith('ulams_pat_', $token['access_token']);
        $this->assertSame('Bearer', $token['token_type']);
        $this->assertSame(['courses:write', 'reports:read'], $token['scopes']);
        $this->assertEqualsWithDelta(30, now()->diffInDays(Carbon::parse($token['expires_at']), false), 1);
        $this->assertNotEmpty($token['token_id']);

        // collected once; the row keeps no credential afterwards
        $row = DeviceAuthorization::query()->firstOrFail();
        $this->assertSame('consumed', $row->status);
        $this->assertNull($row->access_token_encrypted);
        $this->later();
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'expired_token');

        // the token is a real scoped token of the approver, visible in the token list
        $this->app['auth']->forgetGuards();
        $me = $this->getJson('/api/auth/tokens/current', ['Authorization' => 'Bearer ' . $token['access_token']])->assertOk();
        $me->assertJsonPath('data.user.id', $user->getKey())->assertJsonPath('data.scopes', ['courses:write', 'reports:read'])
            ->assertJsonPath('data.created_via', 'device')->assertJsonPath('data.kind', 'cli');
        $list = $this->actingAs($user, 'api')->getJson('/api/auth/tokens')->assertOk();
        $this->assertSame([$token['token_id']], array_column($list->json('data'), 'id'));
        $this->assertSame('device', $list->json('data.0.created_via'));
        $this->assertSame('ulams-cli on mateusz-mbp', $list->json('data.0.name'));
    }

    public function testApprovedScopesAreIntersectedWithRequested(): void
    {
        $user = $this->makeInstructor();
        $res = $this->start(['courses:read']);
        $code = $res['user_code'];
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$code}/approve", ['scopes' => ['users:read', 'settings:write']])->assertStatus(422);
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$code}/approve", ['scopes' => ['courses:write', 'courses:read', 'users:read']])
            ->assertOk()->assertJsonPath('data.scopes', ['courses:read']);

        $star = $this->start(['@admin']);
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$star['user_code']}/approve", ['scopes' => ['courses:read']])->assertOk()->assertJsonPath('data.scopes', ['courses:read']);
    }

    public function testDenyAnswersAccessDenied(): void
    {
        $user = $this->makeStudent();
        $res = $this->start();
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$res['user_code']}/deny")->assertOk()->assertJsonPath('data.status', 'denied');
        $this->later();
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'access_denied');
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$res['user_code']}/approve", ['scopes' => ['courses:write']])->assertStatus(404);
        $this->assertSame(0, DB::table('api_token_meta')->count());
    }

    public function testCodesExpireAfterTenMinutes(): void
    {
        $user = $this->makeStudent();
        $res = $this->start();
        Carbon::setTestNow(now()->addSeconds(601));
        $this->actingAs($user, 'api')->getJson("/api/auth/device/requests/{$res['user_code']}")->assertStatus(404);
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$res['user_code']}/approve", ['scopes' => ['courses:write']])->assertStatus(404);
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'expired_token');
    }

    public function testAnApprovedTokenThatIsNotCollectedInTimeIsRevoked(): void
    {
        $user = $this->makeStudent();
        $res = $this->start(['learner:read']);
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$res['user_code']}/approve", ['scopes' => ['learner:read']])->assertOk();
        $tokenId = DeviceAuthorization::query()->firstOrFail()->token_id;
        Carbon::setTestNow(now()->addSeconds(700));
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'expired_token');
        $this->assertTrue((bool) app(PersonalAccessTokenServiceContract::class)->find($tokenId)->token->revoked);
        $this->assertNull(DeviceAuthorization::query()->firstOrFail()->access_token_encrypted);
    }

    public function testPruneRevokesUncollectedTokensAndDeletesOldRows(): void
    {
        $user = $this->makeStudent();
        $res = $this->start(['learner:read']);
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$res['user_code']}/approve", ['scopes' => ['learner:read']])->assertOk();
        $tokenId = DeviceAuthorization::query()->firstOrFail()->token_id;
        $this->start(); // stays pending
        Carbon::setTestNow(now()->addMinutes(11));
        $this->artisan('ulams:auth:prune-device-authorizations')->assertSuccessful();
        $this->assertTrue((bool) app(PersonalAccessTokenServiceContract::class)->find($tokenId)->token->revoked);
        $this->assertEqualsCanonicalizing(['expired'], DeviceAuthorization::query()->pluck('status')->unique()->all());
        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('ulams:auth:prune-device-authorizations')->assertSuccessful();
        $this->assertSame(0, DeviceAuthorization::query()->count());
    }

    public function testUserCodesAreMatchedForgivingly(): void
    {
        $user = $this->makeStudent();
        $res = $this->start();
        $typed = strtolower(str_replace('-', ' ', $res['user_code']));
        $this->actingAs($user, 'api')->getJson('/api/auth/device/requests/' . rawurlencode($typed))->assertStatus(404); // spaces are not valid route characters
        $this->actingAs($user, 'api')->getJson('/api/auth/device/requests/' . strtolower($res['user_code']))->assertOk();
        $this->actingAs($user, 'api')->getJson('/api/auth/device/requests/' . str_replace('-', '', $res['user_code']))->assertOk();
        $this->actingAs($user, 'api')->getJson('/api/auth/device/requests/AAAA-AAAA')->assertStatus(404);
    }

    public function testApprovalNeedsALoginAndNeverWorksWithAScopedToken(): void
    {
        $user = $this->makeInstructor();
        $res = $this->start();
        $uri = "/api/auth/device/requests/{$res['user_code']}";
        $this->getJson($uri)->assertStatus(401);
        $this->postJson("{$uri}/approve", ['scopes' => ['courses:write']])->assertStatus(401);
        $this->postJson("{$uri}/deny")->assertStatus(401);

        $issued = app(PersonalAccessTokenServiceContract::class)->issue($user, 'scoped', ['*'], 30);
        $this->app['auth']->forgetGuards();
        $this->postJson("{$uri}/approve", ['scopes' => ['courses:write']], ['Authorization' => 'Bearer ' . $issued->secret])
            ->assertStatus(403)->assertJsonPath('error', 'scope_forbidden');
        $this->assertSame('pending', DeviceAuthorization::query()->firstOrFail()->status);
    }

    public function testApprovalIsThrottledPerUser(): void
    {
        $user = $this->makeStudent();
        $other = $this->makeStudent();
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user, 'api')->getJson('/api/auth/device/requests/BBBB-BBBB')->assertStatus(404);
        }
        $this->actingAs($user, 'api')->getJson('/api/auth/device/requests/BBBB-BBBB')->assertStatus(429);
        $this->actingAs($other, 'api')->getJson('/api/auth/device/requests/BBBB-BBBB')->assertStatus(404);
    }

    public function testExpiryIsValidated(): void
    {
        $user = $this->makeStudent();
        $res = $this->start();
        $uri = "/api/auth/device/requests/{$res['user_code']}/approve";
        $this->actingAs($user, 'api')->postJson($uri, ['scopes' => ['courses:write'], 'expires_in_days' => 366])->assertStatus(422);
        $this->actingAs($user, 'api')->postJson($uri, ['scopes' => ['courses:write'], 'expires_in_days' => 0])->assertStatus(422);
        $this->actingAs($user, 'api')->postJson($uri, ['scopes' => []])->assertStatus(422);
        $this->actingAs($user, 'api')->postJson($uri, ['scopes' => ['bogus:read']])->assertStatus(422);
        $this->actingAs($user, 'api')->postJson($uri, ['scopes' => ['courses:write']])->assertOk(); // default 90 days
        $this->later();
        $token = $this->poll($res['device_code'])->assertOk()->json();
        $this->assertEqualsWithDelta(90, now()->diffInDays(Carbon::parse($token['expires_at']), false), 1);
    }

    public function testACodeOfAnotherTenantIsUnknown(): void
    {
        // Database per tenant, and the codes are hashed with the tenant's APP_KEY: even in one
        // database a code made under another key never resolves (coffee code on oncall).
        $user = $this->makeStudent();
        $res = $this->start();
        $this->actingAs($user, 'api')->getJson("/api/auth/device/requests/{$res['user_code']}")->assertOk();
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->actingAs($user, 'api')->getJson("/api/auth/device/requests/{$res['user_code']}")->assertStatus(404);
        $this->actingAs($user, 'api')->postJson("/api/auth/device/requests/{$res['user_code']}/approve", ['scopes' => ['courses:write']])->assertStatus(404);
        $this->poll($res['device_code'])->assertStatus(400)->assertJsonPath('error', 'expired_token');
    }

    public function testMetaAnnouncesDeviceLogin(): void
    {
        $this->getJson('/api/meta')->assertOk()->assertJsonPath('data.features.deviceLogin', true);
    }
}
