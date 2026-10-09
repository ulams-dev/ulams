<?php

namespace Ulams\Auth\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Ulams\Auth\Models\AgentAuditLog;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

/**
 * Scoped personal access tokens (ADR 0074): create, list, revoke, scope enforcement, audit log.
 * Runs WITH middleware: the scope check and the audit log are route middleware.
 */
class ScopedTokensApiTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function mint(\Ulams\Auth\Models\User $user, array $scopes, int $days = 30, array $extra = []): array
    {
        $issued = app(PersonalAccessTokenServiceContract::class)->issue(
            $user, 'test token', $scopes, $days, $extra['kind'] ?? 'agent', $extra['agent'] ?? 'tester', 'admin', $extra['limit'] ?? null,
        );

        return [$issued->secret, $issued->token->getKey()];
    }

    private function bearer(string $secret): array
    {
        return ['Authorization' => 'Bearer ' . $secret, 'Accept' => 'application/json'];
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function testCreateReturnsThePrefixedSecretOnceAndStoresNoSecret(): void
    {
        $user = $this->makeStudent();
        $res = $this->actingAs($user, 'api')->postJson('/api/auth/tokens', [
            'name' => 'laptop', 'scopes' => ['learner:read'], 'expires_in_days' => 30, 'kind' => 'cli',
        ])->assertCreated();
        $secret = $res->json('data.token');
        $this->assertStringStartsWith('ulams_pat_', $secret);
        $res->assertJsonPath('data.scopes', ['learner:read'])->assertJsonPath('data.kind', 'cli')->assertJsonPath('data.revoked', false);

        $jwt = substr($secret, strlen('ulams_pat_'));
        $this->assertStringNotContainsString($jwt, json_encode(DB::table('oauth_access_tokens')->get()));
        $this->assertStringNotContainsString($jwt, json_encode(DB::table('api_token_meta')->get()));

        $list = $this->actingAs($user, 'api')->getJson('/api/auth/tokens')->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertStringNotContainsString('ulams_pat_', $list->getContent());
        $this->assertArrayNotHasKey('token', $list->json('data.0'));
    }

    public function testCreateValidation(): void
    {
        $user = $this->makeStudent();
        $post = fn (array $body) => $this->actingAs($user, 'api')->postJson('/api/auth/tokens', $body + ['name' => 'x']);
        $post(['scopes' => ['nope:read']])->assertStatus(422);
        $post(['scopes' => []])->assertStatus(422);
        $post(['scopes' => ['courses:read'], 'expires_in_days' => 366])->assertStatus(422);
        $post(['scopes' => ['courses:read'], 'expires_in_days' => 0])->assertStatus(422);
        $post(['scopes' => ['courses:read'], 'kind' => 'weird'])->assertStatus(422);
        $post(['scopes' => ['platform:read']])->assertStatus(422)->assertJsonPath('message', 'Platform scopes can only be granted on a platform host.');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/tokens', ['name' => 'x', 'scopes' => ['courses:read']])->assertStatus(401);

        $ok = $post(['scopes' => ['@author']])->assertCreated();
        $this->assertSame(['builder:write', 'courses:write', 'living-course:write', 'reports:read'], $ok->json('data.scopes'));
        $star = $post(['scopes' => ['courses:read', '*']])->assertCreated();
        $this->assertSame(['*'], $star->json('data.scopes'));
    }

    public function testTokenLimitPerUser(): void
    {
        config(['ulams_auth.max_tokens_per_user' => 2]);
        $user = $this->makeStudent();
        $this->mint($user, ['learner:read']);
        $this->mint($user, ['learner:read']);
        $this->actingAs($user, 'api')->postJson('/api/auth/tokens', ['name' => 'x', 'scopes' => ['learner:read']])->assertStatus(422);
    }

    public function testScopesAreEnforcedPerAreaAndMethod(): void
    {
        $admin = $this->makeAdmin();
        [$read] = $this->mint($admin, ['users:read']);
        [$none] = $this->mint($admin, ['courses:write']);
        [$all] = $this->mint($admin, ['*']);

        $this->getJson('/api/admin/users', $this->bearer($read))->assertOk();
        $this->forgetGuards();
        $res = $this->postJson('/api/admin/users', ['email' => 'a@example.com'], $this->bearer($read))->assertStatus(403);
        $res->assertJsonPath('error', 'scope_missing')->assertJsonPath('required', ['users:write']);
        $this->forgetGuards();
        $this->getJson('/api/admin/users', $this->bearer($none))->assertStatus(403)->assertJsonPath('required', ['users:read']);
        $this->forgetGuards();
        $this->getJson('/api/profile/settings', $this->bearer($read))->assertStatus(403)->assertJsonPath('required', ['learner:read']);
        $this->forgetGuards();
        $this->getJson('/api/admin/users', $this->bearer($all))->assertOk();
    }

    public function testWriteImpliesRead(): void
    {
        $admin = $this->makeAdmin();
        [$write] = $this->mint($admin, ['users:write']);
        $this->getJson('/api/admin/users', $this->bearer($write))->assertOk();
    }

    public function testIdentityEndpointsAreOpenToEveryScopedToken(): void
    {
        $user = $this->makeStudent();
        [$secret] = $this->mint($user, ['courses:read']);
        $this->getJson('/api/profile/me', $this->bearer($secret))->assertOk();
        $this->forgetGuards();
        $this->getJson('/api/auth/tokens', $this->bearer($secret))->assertStatus(403)->assertJsonPath('required', ['tokens:read']);
    }

    public function testCurrentIsPublicAndDescribesTheToken(): void
    {
        $user = $this->makeStudent();
        [$secret, $id] = $this->mint($user, ['courses:read'], 10, ['kind' => 'ci', 'agent' => 'robot']);
        $this->getJson('/api/auth/tokens/current', $this->bearer($secret))
            ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.scoped', true)
            ->assertJsonPath('data.scopes', ['courses:read'])->assertJsonPath('data.kind', 'ci')
            ->assertJsonPath('data.agent_name', 'robot')->assertJsonPath('data.user.id', $user->getKey());
    }

    public function testUnmappedRoutesAreDeniedFailClosed(): void
    {
        Route::middleware('auth:api')->get('api/zz-unmapped', fn () => ['ok' => true]);
        $user = $this->makeStudent();
        [$secret] = $this->mint($user, ['*']);
        $this->getJson('/api/zz-unmapped', $this->bearer($secret))->assertStatus(403)->assertJsonPath('error', 'scope_unmapped');
    }

    public function testRoutesThatMintUnscopedCredentialsAreNeverAvailable(): void
    {
        $user = $this->makeStudent();
        [$secret] = $this->mint($user, ['*']);
        $this->getJson('/api/auth/refresh', $this->bearer($secret))->assertStatus(403)->assertJsonPath('error', 'scope_forbidden');
        $this->forgetGuards();
        $this->putJson('/api/profile/password', [], $this->bearer($secret))->assertStatus(403)->assertJsonPath('error', 'scope_forbidden');
    }

    public function testUnscopedLoginTokensAreUnaffected(): void
    {
        $user = $this->makeStudent();
        $login = app(AuthServiceContract::class)->createTokenForUser($user)->accessToken;
        $this->getJson('/api/profile/settings', $this->bearer($login))->assertOk();
        $this->forgetGuards();
        $this->getJson('/api/auth/tokens/current', $this->bearer($login))->assertOk()->assertJsonPath('data.scoped', false)->assertJsonPath('data.scopes', ['*']);
    }

    public function testBareJwtAndPrefixedTokensBothAuthenticate(): void
    {
        $user = $this->makeStudent();
        [$secret] = $this->mint($user, ['learner:read']);
        $this->getJson('/api/profile/settings', $this->bearer($secret))->assertOk();
        $this->forgetGuards();
        $this->getJson('/api/profile/settings', $this->bearer(substr($secret, strlen('ulams_pat_'))))->assertOk();
    }

    public function testScopesNeverWidenThePermissionsOfTheUser(): void
    {
        $student = $this->makeStudent();
        [$secret] = $this->mint($student, ['*']);
        $this->getJson('/api/admin/users', $this->bearer($secret))->assertStatus(403);
    }

    public function testRevokedTokensAreRefusedAndOwnersCanRevoke(): void
    {
        $user = $this->makeStudent();
        [$secret, $id] = $this->mint($user, ['tokens:write', 'learner:read']);
        $this->getJson('/api/profile/settings', $this->bearer($secret))->assertOk();
        $this->forgetGuards();
        $this->deleteJson("/api/auth/tokens/{$id}", [], $this->bearer($secret))->assertOk();
        $this->forgetGuards();
        $this->getJson('/api/profile/settings', $this->bearer($secret))->assertStatus(401);

        $this->actingAs($user, 'api')->getJson('/api/auth/tokens')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($user, 'api')->getJson('/api/auth/tokens?include_revoked=1')->assertOk()->assertJsonPath('data.0.revoked', true);
    }

    public function testUsersCannotSeeOrRevokeEachOthersTokens(): void
    {
        $a = $this->makeStudent();
        $b = $this->makeStudent();
        [, $idA] = $this->mint($a, ['learner:read']);
        $this->actingAs($b, 'api')->getJson('/api/auth/tokens')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($b, 'api')->deleteJson("/api/auth/tokens/{$idA}")->assertStatus(403);
        $this->actingAs($b, 'api')->deleteJson('/api/auth/tokens/' . str_repeat('a', 80))->assertStatus(404);
        $this->actingAs($b, 'api')->deleteJson('/api/auth/tokens/not%20valid!')->assertStatus(404);
        $this->assertFalse((bool) app(PersonalAccessTokenServiceContract::class)->find($idA)->token->revoked);
    }

    public function testAdminListsAndRevokesEverythingStudentsAndGuestsCannot(): void
    {
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        [, $id] = $this->mint($student, ['learner:read']);

        $this->actingAs($student, 'api')->getJson('/api/admin/tokens')->assertStatus(403);
        $this->actingAs($student, 'api')->deleteJson("/api/admin/tokens/{$id}")->assertStatus(200); // owner may revoke their own
        $this->actingAs($student, 'api')->getJson('/api/admin/agent-audit')->assertStatus(403);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/tokens')->assertStatus(401);

        [, $id2] = $this->mint($student, ['learner:read']);
        $list = $this->actingAs($admin, 'api')->getJson('/api/admin/tokens?user_id=' . $student->getKey())->assertOk();
        $this->assertSame([$id2], array_column($list->json('data'), 'id'));
        $this->actingAs($admin, 'api')->deleteJson("/api/admin/tokens/{$id2}")->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/admin/tokens?include_revoked=1&kind=agent')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($admin, 'api')->getJson('/api/admin/tokens?kind=bogus')->assertStatus(422);
    }

    public function testATokenCannotGrantMoreThanItHas(): void
    {
        $user = $this->makeInstructor();
        [$secret] = $this->mint($user, ['tokens:write', 'courses:read']);
        $this->postJson('/api/auth/tokens', ['name' => 'child', 'scopes' => ['courses:write']], $this->bearer($secret))->assertStatus(422);
        $this->forgetGuards();
        $this->postJson('/api/auth/tokens', ['name' => 'child', 'scopes' => ['courses:read']], $this->bearer($secret))->assertCreated();
        $this->forgetGuards();
        $this->postJson('/api/auth/tokens', ['name' => 'child', 'scopes' => ['*']], $this->bearer($secret))->assertStatus(422);
    }

    public function testMutationsAndPersonalDataReadsAreAudited(): void
    {
        $admin = $this->makeAdmin();
        [$secret, $id] = $this->mint($admin, ['users:write', 'learner:read'], 30, ['agent' => 'claude-code']);
        $hdr = $this->bearer($secret) + ['X-Ulams-Client' => 'cli', 'Idempotency-Key' => 'k-1', 'X-Request-Id' => '01JABCDEFGHJKMNPQRSTVWXYZ0'];

        $this->getJson('/api/profile/settings', $hdr)->assertOk(); // learner read: not audited
        $this->assertSame(0, AgentAuditLog::query()->count());
        $this->forgetGuards();
        $this->getJson('/api/admin/users', $hdr)->assertOk(); // users read: audited
        $this->forgetGuards();
        $this->postJson('/api/admin/users', ['email' => 'secret-body@example.com', 'password' => 'hunter2hunter2'], $hdr);

        $rows = AgentAuditLog::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['GET', 'POST'], $rows->pluck('method')->all());
        $post = $rows[1];
        $this->assertSame($id, $post->token_id);
        $this->assertSame($admin->getKey(), $post->user_id);
        $this->assertSame('claude-code', $post->agent_name);
        $this->assertSame('cli', $post->client);
        $this->assertSame('k-1', $post->idempotency_key);
        $this->assertSame('/api/admin/users', $post->path);
        $this->assertNotNull($post->duration_ms);
        $this->assertStringNotContainsString('secret-body', json_encode($rows->toArray()));
        $this->assertStringNotContainsString('hunter2', json_encode($rows->toArray()));
        $this->assertNotNull(ApiTokenMeta::query()->where('token_id', $id)->first()->last_used_at);

        $this->forgetGuards();
        $this->deleteJson('/api/admin/users/999999', [], $this->bearer($secret))->assertStatus(422); // failed writes are logged too
        $this->assertSame(3, AgentAuditLog::query()->count());
        $this->assertSame(422, AgentAuditLog::query()->orderByDesc('id')->first()->status);
    }

    public function testScopeDenialsAreAuditedToo(): void
    {
        $admin = $this->makeAdmin();
        [$secret] = $this->mint($admin, ['users:read']);
        $this->postJson('/api/admin/users', [], $this->bearer($secret))->assertStatus(403);
        $this->assertSame(403, AgentAuditLog::query()->latest('id')->first()->status);
    }

    public function testAdminReadsTheAuditLogWithFilters(): void
    {
        $admin = $this->makeAdmin();
        $other = $this->makeInstructor();
        [$s1, $id1] = $this->mint($admin, ['users:write']);
        $this->postJson('/api/admin/users', [], $this->bearer($s1));
        $this->forgetGuards();

        $this->actingAs($admin, 'api')->getJson("/api/admin/tokens/{$id1}/audit")->assertOk()->assertJsonPath('data.0.method', 'POST');
        $this->actingAs($admin, 'api')->getJson("/api/admin/agent-audit?token_id={$id1}")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'api')->getJson('/api/admin/agent-audit?token_id=zzz')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin, 'api')->getJson('/api/admin/agent-audit?user_id=' . $admin->getKey() . '&from=2000-01-01&to=2100-01-01')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'api')->getJson('/api/admin/agent-audit?from=2100-01-01&to=2000-01-01')->assertStatus(422);
        $this->actingAs($other, 'api')->getJson("/api/admin/tokens/{$id1}/audit")->assertStatus(403);
        $this->actingAs($other, 'api')->getJson('/api/admin/agent-audit')->assertStatus(403);
    }

    public function testAuditRowsArePrunedAfterTheRetentionPeriod(): void
    {
        AgentAuditLog::query()->create(['method' => 'POST', 'path' => '/x', 'status' => 200, 'created_at' => now()->subDays(400)]);
        AgentAuditLog::query()->create(['method' => 'POST', 'path' => '/y', 'status' => 200, 'created_at' => now()]);
        $this->artisan('ulams:auth:prune-agent-audit')->assertSuccessful();
        $this->assertSame(['/y'], AgentAuditLog::query()->pluck('path')->all());
    }

    public function testPerTokenRateLimit(): void
    {
        $user = $this->makeStudent();
        [$secret] = $this->mint($user, ['learner:read'], 30, ['limit' => 2]);
        $this->getJson('/api/profile/settings', $this->bearer($secret))->assertOk();
        $this->forgetGuards();
        $this->getJson('/api/profile/settings', $this->bearer($secret))->assertOk();
        $this->forgetGuards();
        $this->getJson('/api/profile/settings', $this->bearer($secret))->assertStatus(429)->assertJsonPath('error', 'rate_limited');
    }

    public function testMetaDescribesTheHost(): void
    {
        $res = $this->getJson('/api/meta')->assertOk()->assertJsonPath('data.api', 'ulams')->assertJsonPath('data.contract', 1)
            ->assertJsonPath('data.kind', 'tenant')->assertJsonPath('data.features.scopedTokens', true)
            ->assertJsonPath('data.features.idempotency', true)->assertJsonPath('data.features.platformApi', false);
        $this->assertIsBool($res->json('data.features.deviceLogin'));

        config(['ulams_tenancy.platform_hosts' => ['localhost']]);
        $this->getJson('/api/meta')->assertJsonPath('data.kind', 'platform');
    }

    public function testPlatformHostsCapLifetimeAtThirtyDays(): void
    {
        config(['ulams_tenancy.platform_hosts' => ['localhost']]);
        $user = $this->makeAdmin();
        $post = fn (int $days) => $this->actingAs($user, 'api')->postJson('/api/auth/tokens', ['name' => 'p', 'scopes' => ['platform:read'], 'expires_in_days' => $days]);
        $post(31)->assertStatus(422);
        $post(30)->assertCreated();
    }

    public function testRequestIdIsEchoedOrGenerated(): void
    {
        $id = '01JABCDEFGHJKMNPQRSTVWXYZ0';
        $this->getJson('/api/meta', ['X-Request-Id' => $id])->assertHeader('X-Request-Id', $id);
        $generated = $this->getJson('/api/meta', ['X-Request-Id' => 'not valid'])->headers->get('X-Request-Id');
        $this->assertNotSame('not valid', $generated);
        $this->assertTrue(\Illuminate\Support\Str::isUlid($generated));
    }
}
