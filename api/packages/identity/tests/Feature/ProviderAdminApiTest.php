<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityLink;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Tests\Support\FakeIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\TestCase;

class ProviderAdminApiTest extends TestCase
{
    use RunsOidcLogin;

    private const BASE = '/api/admin/identity/providers';

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'slug' => 'acme-keycloak',
            'name' => 'Acme staff',
            'enabled' => true,
            'email_domains' => ['Example.com'],
            'trust_email' => false,
            'oidc' => ['issuer' => FakeIdp::ISSUER, 'client_id' => FakeIdp::CLIENT_ID, 'scopes' => ['email', 'profile', 'groups']],
            'client_secret' => FakeIdp::CLIENT_SECRET,
        ], $overrides);
    }

    private function create(array $overrides = [])
    {
        return $this->actingAs($this->admin(), 'api')->postJson(self::BASE, $this->payload($overrides));
    }

    public function testAnAdminCreatesAProviderAndTheSecretIsWriteOnly(): void
    {
        $response = $this->create()->assertCreated();

        $data = $response->json('data');
        $this->assertSame(['acme-keycloak', 'Acme staff', 'oidc', true, ['example.com']], [$data['slug'], $data['name'], $data['protocol'], $data['enabled'], $data['email_domains']]);
        $this->assertTrue($data['client_secret_set']);
        $this->assertSame(substr(FakeIdp::CLIENT_SECRET, -4), $data['client_secret_hint']);
        $this->assertSame('http://localhost/api/identity/oidc/acme-keycloak/callback', $data['redirect_uri']);
        $this->assertSame(['email', 'profile', 'groups'], $data['oidc']['scopes']);
        $this->assertSame('client_secret_basic', $data['oidc']['token_auth']);
        $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, $response->getContent());
        $this->assertArrayNotHasKey('client_secret', $data);
        $this->assertArrayNotHasKey('oidc_client_secret', $data);
    }

    public function testTheSecretIsEncryptedAtRestAndNeverComesBackFromAnyEndpoint(): void
    {
        $id = $this->create()->json('data.id');

        $raw = (string) DB::table('identity_providers')->where('id', $id)->value('oidc_client_secret');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, $raw);
        $this->assertSame(FakeIdp::CLIENT_SECRET, IdentityProvider::query()->findOrFail($id)->oidc_client_secret);
        $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, json_encode(IdentityProvider::query()->findOrFail($id)->toArray()));

        $admin = $this->admin();
        foreach ([self::BASE, self::BASE . '/' . $id, '/api/admin/identity/audit', '/api/identity/providers'] as $uri) {
            $this->actingAs($admin, 'api')->getJson($uri)->assertOk();
            $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, $this->actingAs($admin, 'api')->getJson($uri)->getContent(), $uri);
        }
        $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, json_encode(DB::table('identity_audit')->get()));
    }

    public function testAShortSecretHasNoHint(): void
    {
        $data = $this->create(['client_secret' => 'short'])->assertCreated()->json('data');

        $this->assertTrue($data['client_secret_set']);
        $this->assertNull($data['client_secret_hint']);
    }

    public function testUpdateKeepsTheSecretWhenNoneIsSentAndReplacesItWhenOneIs(): void
    {
        $id = $this->create()->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $id, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->assertSame(FakeIdp::CLIENT_SECRET, IdentityProvider::query()->findOrFail($id)->oidc_client_secret);

        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $id, ['client_secret' => 'a-brand-new-secret-value-0000'])->assertOk()->assertJsonPath('data.client_secret_hint', '0000');
        $this->assertSame('a-brand-new-secret-value-0000', IdentityProvider::query()->findOrFail($id)->oidc_client_secret);

        $row = IdentityAudit::query()->where('action', 'provider.updated')->latest('id')->firstOrFail();
        $this->assertTrue($row->data['secret_changed']);
        $this->assertStringNotContainsString('brand-new', json_encode($row->data));
    }

    public function testTheSlugIsFixedAfterCreation(): void
    {
        $id = $this->create()->json('data.id');

        $this->actingAs($this->admin(), 'api')->putJson(self::BASE . '/' . $id, ['slug' => 'hijacked', 'name' => 'X'])->assertOk()->assertJsonPath('data.slug', 'acme-keycloak');
    }

    public function testAnOidcSettingChangeKeepsTheOtherSettings(): void
    {
        $id = $this->create()->json('data.id');

        $this->actingAs($this->admin(), 'api')->putJson(self::BASE . '/' . $id, ['oidc' => ['client_id' => 'new-client']])->assertOk()
            ->assertJsonPath('data.oidc.client_id', 'new-client')
            ->assertJsonPath('data.oidc.issuer', FakeIdp::ISSUER)
            ->assertJsonPath('data.oidc.scopes', ['email', 'profile', 'groups']);
    }

    public function testDeletingAProviderRemovesItsLinksButNotTheAccounts(): void
    {
        $this->newVerifier();
        $provider = $this->provider();
        $this->signIn();
        $this->assertSame(1, IdentityLink::query()->count());

        $this->actingAs($this->admin(), 'api')->deleteJson(self::BASE . '/' . $provider->getKey())->assertOk();

        $this->assertSame(0, IdentityLink::query()->count());
        $this->assertTrue(\Ulams\Auth\Models\User::query()->where('email', 'ada@example.com')->exists());
        $row = IdentityAudit::query()->where('action', 'provider.deleted')->firstOrFail();
        $this->assertSame(1, $row->data['links_removed']);
        $this->start()->assertStatus(404);
    }

    public function testValidation(): void
    {
        $admin = $this->admin();
        $post = fn (array $o) => $this->actingAs($admin, 'api')->postJson(self::BASE, $this->payload($o));

        $post(['slug' => 'Not Valid'])->assertStatus(422)->assertJsonValidationErrors('slug');
        $post(['slug' => '-leading'])->assertStatus(422);
        $post(['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $post(['oidc' => ['issuer' => 'http://idp.example.test']])->assertStatus(422)->assertJsonValidationErrors('oidc.issuer');
        $post(['oidc' => ['issuer' => 'https://user:pw@idp.example.test']])->assertStatus(422);
        $post(['oidc' => ['issuer' => 'https://idp.example.test/?x=1']])->assertStatus(422);
        $post(['oidc' => ['issuer' => 'javascript:alert(1)']])->assertStatus(422);
        $post(['oidc' => ['client_id' => '']])->assertStatus(422);
        $post(['oidc' => ['scopes' => ['bad scope']]])->assertStatus(422);
        $post(['oidc' => ['token_auth' => 'private_key_jwt']])->assertStatus(422);
        $post(['email_domains' => ['not a domain']])->assertStatus(422);
        $post(['default_role' => 'admin'])->assertStatus(422)->assertJsonValidationErrors('default_role');
        $this->assertSame(0, IdentityProvider::query()->count());

        $post([])->assertCreated();
        $post([])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function testAnHttpIssuerIsAllowedOnlyWhenInsecureUrlsAreSwitchedOn(): void
    {
        config(['ulams_identity.allow_insecure_urls' => true]);

        $this->create(['oidc' => ['issuer' => 'http://keycloak:8080/realms/ulams']])->assertCreated();
    }

    public function testEnablingNeedsEverythingALoginNeeds(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->postJson(self::BASE, $this->payload(['client_secret' => null]))->assertStatus(422)->assertJsonValidationErrors('enabled');
        $id = $this->actingAs($admin, 'api')->postJson(self::BASE, $this->payload(['client_secret' => null, 'enabled' => false]))->assertCreated()->json('data.id');
        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $id, ['enabled' => true])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $id, ['enabled' => true, 'client_secret' => FakeIdp::CLIENT_SECRET])->assertOk()->assertJsonPath('data.enabled', true);
    }

    public function testAnAcademyHasAMaximumNumberOfProviders(): void
    {
        config(['ulams_identity.max_providers' => 2]);
        $this->create(['slug' => 'one'])->assertCreated();
        $this->create(['slug' => 'two'])->assertCreated();

        $this->create(['slug' => 'three'])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function testEveryChangeIsAudited(): void
    {
        $admin = $this->admin();
        $id = $this->create()->json('data.id');
        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $id, ['name' => 'Renamed', 'trust_email' => true])->assertOk();
        $this->actingAs($admin, 'api')->deleteJson(self::BASE . '/' . $id)->assertOk();

        $rows = IdentityAudit::query()->orderBy('id')->get();
        $this->assertSame(['provider.created', 'provider.updated', 'provider.deleted'], $rows->pluck('action')->all());
        $this->assertSame(['user'], $rows->pluck('actor')->unique()->values()->all());
        $this->assertSame($id, $rows[0]->provider_id);
        $this->assertEqualsCanonicalizing(['name', 'trust_email'], $rows[1]->data['fields']);
    }

    public function testTheAuditTrailCanBeListedFilteredAndVerified(): void
    {
        $admin = $this->admin();
        $id = $this->create()->json('data.id');
        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $id, ['name' => 'Renamed'])->assertOk();

        $list = $this->actingAs($admin, 'api')->getJson('/api/admin/identity/audit')->assertOk()->json('data');
        $this->assertSame(['provider.updated', 'provider.created'], array_column($list, 'action'));
        $this->assertSame(['provider.created'], array_column($this->actingAs($admin, 'api')->getJson('/api/admin/identity/audit?action=provider.created')->json('data'), 'action'));
        $this->assertCount(1, $this->actingAs($admin, 'api')->getJson('/api/admin/identity/audit?limit=1&before_id=' . $list[0]['id'])->json('data'));
        $this->actingAs($admin, 'api')->getJson('/api/admin/identity/audit?action=nope')->assertStatus(422);

        $this->actingAs($admin, 'api')->getJson('/api/admin/identity/audit/verify')->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.checked', 2);
    }

    public function testNoSecretReachesTheLogs(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'idlog');
        config(['logging.default' => 'single', 'logging.channels.single.path' => $path, 'logging.channels.single.level' => 'debug']);
        $this->app['log']->forgetChannel('single');

        $this->provider();
        $this->signIn(['aud' => 'x']);
        $this->idp->discovery = ['issuer' => 'https://evil.example/'];
        \Illuminate\Support\Facades\Cache::flush();
        $this->newVerifier();
        $this->start()->assertStatus(502);
        $this->create(['slug' => 'logged'])->assertCreated();

        $log = (string) file_get_contents($path);
        @unlink($path);
        $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, $log);
        $this->assertStringNotContainsString('access-token-1', $log);
        $this->assertStringNotContainsString('eyJ', $log);
        $this->assertStringContainsString('OpenID Connect step failed', $log);
    }
}
