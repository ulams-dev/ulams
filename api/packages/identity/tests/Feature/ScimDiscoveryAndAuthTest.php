<?php

namespace Ulams\Identity\Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Services\AuthService;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Tests\Support\RunsScim;
use Ulams\Identity\Tests\TestCase;

class ScimDiscoveryAndAuthTest extends TestCase
{
    use RunsScim;

    private function ready(): void
    {
        $this->scimToken($this->scimProvider());
    }

    public function testServiceProviderConfigDescribesWhatIsImplemented(): void
    {
        $this->ready();

        $response = $this->scim('GET', '/ServiceProviderConfig')->assertOk();
        $json = $response->json();

        $this->assertStringStartsWith('application/scim+json', $response->headers->get('Content-Type'));
        $this->assertSame([Schemas::CONFIG], $json['schemas']);
        $this->assertTrue($json['patch']['supported']);
        $this->assertFalse($json['bulk']['supported']);
        $this->assertSame(['supported' => true, 'maxResults' => 200], $json['filter']);
        $this->assertFalse($json['changePassword']['supported']);
        $this->assertFalse($json['sort']['supported']);
        $this->assertTrue($json['etag']['supported']);
        $this->assertSame('oauthbearertoken', $json['authenticationSchemes'][0]['type']);
        $this->assertTrue($json['authenticationSchemes'][0]['primary']);
        $this->assertSame('http://localhost/api/scim/v2/ServiceProviderConfig', $json['meta']['location']);
    }

    public function testResourceTypesAndSchemasAreListedAndFetchableById(): void
    {
        $this->ready();

        $types = $this->scim('GET', '/ResourceTypes')->assertOk()->json();
        $this->assertSame(2, $types['totalResults']);
        $this->assertSame(['User', 'Group'], array_column($types['Resources'], 'id'));
        $this->assertSame(Schemas::ENTERPRISE, $types['Resources'][0]['schemaExtensions'][0]['schema']);
        $this->assertFalse($types['Resources'][0]['schemaExtensions'][0]['required']);
        $this->scim('GET', '/ResourceTypes/Group')->assertOk()->assertJsonPath('endpoint', '/Groups');
        $this->scim('GET', '/ResourceTypes/Nope')->assertStatus(404)->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:Error');

        $schemas = $this->scim('GET', '/Schemas')->assertOk()->json();
        $this->assertSame([Schemas::USER, Schemas::ENTERPRISE, Schemas::GROUP], array_column($schemas['Resources'], 'id'));
        $this->scim('GET', '/Schemas/' . Schemas::ENTERPRISE)->assertOk()->assertJsonPath('name', 'EnterpriseUser');
        $this->scim('GET', '/Schemas/urn:nope')->assertStatus(404);
        $user = collect($schemas['Resources'][0]['attributes'])->firstWhere('name', 'userName');
        $this->assertTrue($user['required']);
        $this->assertSame('server', $user['uniqueness']);
    }

    public function testErrorsUseTheScimErrorFormat(): void
    {
        $this->ready();

        $error = $this->scim('GET', '/Users/99999')->assertStatus(404)->json();

        $this->assertSame(['schemas' => [\Ulams\Identity\Services\Scim\ScimError::SCHEMA], 'status' => '404', 'detail' => 'User 99999 not found.'], $error);
    }

    public function testNoTokenIsA401InTheScimFormatWithAChallenge(): void
    {
        $this->scimProvider();

        $response = $this->scim('GET', '/Users')->assertStatus(401);

        $this->assertSame('Bearer realm="scim"', $response->headers->get('WWW-Authenticate'));
        $this->assertSame('401', $response->json('status'));
        $this->scim('GET', '/Users', null, [], 'ulams_pat_not-a-token')->assertStatus(401);
    }

    public function testOnlyATokenOfKindScimWorksOnScimRoutes(): void
    {
        $provider = $this->scimProvider();
        $admin = $this->admin();
        $issued = app(PersonalAccessTokenServiceContract::class)->issue($admin, 'cli', ['*'], 30, 'cli');
        $login = app(AuthService::class)->createTokenForUser($admin);

        $this->scim('GET', '/Users', null, [], $issued->secret)->assertStatus(403);
        $this->scim('GET', '/ServiceProviderConfig', null, [], $login->accessToken ?? (string) $login)->assertStatus(403);
        $this->assertSame('403', $this->scim('GET', '/Users', null, [], $issued->secret)->json('status'));
        $this->assertSame(0, \Ulams\Identity\Models\ExternalId::query()->count());
    }

    public function testAScimTokenWorksNowhereElse(): void
    {
        $this->ready();

        foreach ([['GET', '/api/profile/me'], ['GET', '/api/admin/identity/providers'], ['GET', '/api/identity/providers'], ['POST', '/api/auth/logout'], ['GET', '/api/admin/users']] as [$method, $uri]) {
            $this->app['auth']->forgetGuards();
            $response = $this->call($method, $uri, [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->scimSecret, 'HTTP_ACCEPT' => 'application/json']);
            $this->assertSame(403, $response->getStatusCode(), "$method $uri");
            $this->assertSame('scope_forbidden', $response->json('error'), "$method $uri");
        }
    }

    public function testATokenSignedByAnotherTenantsKeysIsNotAToken(): void
    {
        $this->ready();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $jwt = JWT::encode(['aud' => '1', 'jti' => str_repeat('a', 80), 'iat' => time(), 'nbf' => time(), 'exp' => time() + 3600, 'sub' => '1', 'scopes' => ['scim:write']], $pem, 'RS256');

        $this->scim('GET', '/Users', null, [], 'ulams_pat_' . $jwt)->assertStatus(401);
        $this->scim('GET', '/Users', null, [], $jwt)->assertStatus(401);
    }

    public function testARevokedTokenAndADeletedProviderStopWorking(): void
    {
        $provider = $this->scimProvider();
        $secret = $this->scimToken($provider);
        $this->scim('GET', '/Users')->assertOk();

        $admin = $this->admin();
        $this->actingAs($admin, 'api')->deleteJson('/api/admin/identity/providers/' . $provider->id)->assertOk();
        $this->scim('GET', '/Users', null, [], $secret)->assertStatus(401);
        $audit = IdentityAudit::query()->where('action', 'provider.deleted')->firstOrFail();
        $this->assertSame(1, $audit->data['scim_tokens_revoked']);
    }

    public function testTheRateLimitIsPerTokenAndAnswersInTheScimFormat(): void
    {
        config(['ulams_identity.scim.rate_limit' => 3]);
        $provider = $this->scimProvider();
        $first = $this->scimToken($provider, 'one');
        $second = $this->scimToken($provider, 'two');

        for ($i = 0; $i < 3; $i++) {
            $this->scim('GET', '/ServiceProviderConfig', null, [], $first)->assertOk();
        }
        $limited = $this->scim('GET', '/ServiceProviderConfig', null, [], $first)->assertStatus(429);

        $this->assertSame('429', $limited->json('status'));
        $this->assertNotNull($limited->headers->get('Retry-After'));
        $this->scim('GET', '/ServiceProviderConfig', null, [], $second)->assertOk();
    }

    // --- token management by an administrator -----------------------------------------------------------------

    private function tokens(string $id): string
    {
        return '/api/admin/identity/providers/' . $id . '/scim-tokens';
    }

    public function testAnAdministratorCreatesAScimTokenShownOnceAndBoundToTheProvider(): void
    {
        $provider = $this->scimProvider();
        $admin = $this->admin();

        $created = $this->actingAs($admin, 'api')->postJson($this->tokens($provider->id), ['name' => 'Entra ID', 'expires_in_days' => 200])->assertCreated();
        $secret = $created->json('data.token');

        $this->assertStringStartsWith('ulams_pat_', $secret);
        $this->assertSame('scim', $created->json('data.kind'));
        $this->assertSame(['scim:write'], $created->json('data.scopes'));
        $this->assertEqualsWithDelta(now()->addDays(200)->timestamp, strtotime($created->json('data.expires_at')), 120);
        $this->scim('GET', '/Users', null, [], $secret)->assertOk();

        $list = $this->actingAs($admin, 'api')->getJson($this->tokens($provider->id))->assertOk();
        $this->assertSame([$created->json('data.id')], array_column($list->json('data'), 'id'));
        $this->assertStringNotContainsString($secret, $list->getContent());
        $this->assertStringNotContainsString($secret, json_encode(DB::table('identity_audit')->get()));
        $this->assertStringNotContainsString($secret, json_encode(DB::table('oauth_access_tokens')->get()));
        $this->assertSame(1, $this->actingAs($admin, 'api')->getJson('/api/admin/identity/providers/' . $provider->id)->json('data.scim.active_tokens'));
        $audit = IdentityAudit::query()->where('action', 'scim.token_created')->firstOrFail();
        $this->assertSame($created->json('data.id'), $audit->data['token_id']);
        $this->assertSame($admin->id, $audit->actor_id);
    }

    public function testTheServiceUserCannotSignInAndOwnsOnlyTheToken(): void
    {
        $provider = $this->scimProvider();
        $this->scimToken($provider);
        $service = \Ulams\Auth\Models\User::query()->findOrFail($provider->refresh()->scim_user_id);

        $this->assertSame('scim+acme@identity.invalid', $service->email);
        $this->assertNull($service->password);
        $this->assertFalse($service->is_active);
        $this->assertNull($service->email_verified_at);
        $this->assertSame([], $service->getRoleNames()->all());
        $this->postJson('/api/auth/login', ['email' => $service->email, 'password' => ''])->assertStatus(422)->assertJsonMissing(['token']);
    }

    public function testAtMostTwoActiveTokensSoASecretCanBeRotated(): void
    {
        $provider = $this->scimProvider();
        $admin = $this->admin();
        $a = $this->actingAs($admin, 'api')->postJson($this->tokens($provider->id), ['name' => 'a'])->assertCreated();
        $b = $this->actingAs($admin, 'api')->postJson($this->tokens($provider->id), ['name' => 'b'])->assertCreated();

        $this->actingAs($admin, 'api')->postJson($this->tokens($provider->id), ['name' => 'c'])->assertStatus(422);

        $this->actingAs($admin, 'api')->deleteJson($this->tokens($provider->id) . '/' . $a->json('data.id'))->assertOk();
        $this->scim('GET', '/Users', null, [], $a->json('data.token'))->assertStatus(401);
        $this->scim('GET', '/Users', null, [], $b->json('data.token'))->assertOk();
        $this->actingAs($admin, 'api')->postJson($this->tokens($provider->id), ['name' => 'c'])->assertCreated();
        $this->assertSame(['scim.token_created', 'scim.token_created', 'scim.token_revoked', 'scim.token_created'], IdentityAudit::query()->orderBy('id')->pluck('action')->all());
    }

    public function testATokenLivesAtMost365Days(): void
    {
        $provider = $this->scimProvider();

        $this->actingAs($this->admin(), 'api')->postJson($this->tokens($provider->id), ['name' => 'x', 'expires_in_days' => 366])->assertStatus(422);
        $this->actingAs($this->admin(), 'api')->postJson($this->tokens($provider->id), ['name' => 'x', 'expires_in_days' => 0])->assertStatus(422);
        $this->actingAs($this->admin(), 'api')->postJson($this->tokens($provider->id), [])->assertStatus(422);
    }

    public function testAScimTokenCannotBeMadeThroughTheGeneralTokenApi(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->postJson('/api/auth/tokens', ['name' => 'x', 'scopes' => ['scim:write'], 'kind' => 'scim'])->assertStatus(422);
        // a general token with the scope is not a SCIM token, so it is no use on the SCIM routes
        $issued = $this->actingAs($admin, 'api')->postJson('/api/auth/tokens', ['name' => 'x', 'scopes' => ['scim:write']]);
        if ($issued->getStatusCode() === 201) {
            $this->scimProvider();
            $this->scim('GET', '/Users', null, [], $issued->json('data.token'))->assertStatus(403);
        }
        $this->assertSame(0, ApiTokenMeta::query()->where('kind', 'scim')->count());
    }

    public function testTheTokenEndpointsNeedThePermissionAndAnotherTenantsProviderIdIsUnknown(): void
    {
        $provider = $this->scimProvider();
        $token = $this->actingAs($this->admin(), 'api')->postJson($this->tokens($provider->id), ['name' => 'a'])->json('data');
        $student = $this->student();
        $tutor = $this->tutor();

        foreach ([$student, $tutor] as $user) {
            $this->actingAs($user, 'api')->getJson($this->tokens($provider->id))->assertStatus(403);
            $this->actingAs($user, 'api')->postJson($this->tokens($provider->id), ['name' => 'x'])->assertStatus(403);
            $this->actingAs($user, 'api')->deleteJson($this->tokens($provider->id) . '/' . $token['id'])->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        $this->getJson($this->tokens($provider->id))->assertStatus(401);
        $this->postJson($this->tokens($provider->id), ['name' => 'x'])->assertStatus(401);
        $this->deleteJson($this->tokens($provider->id) . '/' . $token['id'])->assertStatus(401);

        $other = '2f1c9e1e-3b7a-4c8e-9d3f-5a6b7c8d9e0f';
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->getJson($this->tokens($other))->assertStatus(404);
        $this->actingAs($admin, 'api')->postJson($this->tokens($other), ['name' => 'x'])->assertStatus(404);
        $this->actingAs($admin, 'api')->deleteJson($this->tokens($provider->id) . '/not-a-token')->assertStatus(404);
        $this->actingAs($admin, 'api')->getJson($this->tokens('nope'))->assertStatus(404);
        // the identity_scim_manage permission is separate from managing providers
        $manager = $this->student();
        $manager->givePermissionTo('identity_provider_manage');
        $this->actingAs($manager, 'api')->postJson($this->tokens($provider->id), ['name' => 'x'])->assertStatus(403);
        $scimManager = $this->student();
        $scimManager->givePermissionTo('identity_scim_manage');
        $this->actingAs($scimManager, 'api')->getJson($this->tokens($provider->id))->assertOk();
    }

    public function testATokenOfOneProviderIsNotATokenOfAnother(): void
    {
        $a = $this->scimProvider();
        $b = $this->provider(['slug' => 'other', 'name' => 'Other']);
        $tokenA = $this->scimToken($a);
        $tokenB = $this->scimToken($b);

        $user = $this->scim('POST', '/Users', $this->userBody(), [], $tokenA)->assertStatus(201)->json();

        $this->scim('GET', '/Users/' . $user['id'], null, [], $tokenA)->assertOk();
        $this->scim('GET', '/Users/' . $user['id'], null, [], $tokenB)->assertStatus(404);
        $this->assertSame(0, $this->scim('GET', '/Users', null, [], $tokenB)->json('totalResults'));
        $this->scim('DELETE', '/Users/' . $user['id'], null, [], $tokenB)->assertStatus(404);
        $this->scim('PATCH', '/Users/' . $user['id'], $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => false]]), [], $tokenB)->assertStatus(404);
    }
}
