<?php

namespace Ulams\Identity\Tests\Feature;

use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\Support\RunsSamlLogin;
use Ulams\Identity\Tests\TestCase;

/**
 * Access control and tenant isolation for every identity endpoint. A tenant is one database chosen by host, so
 * a provider, state or code of tenant A is simply unknown at tenant B: the host-bound checks below prove the
 * state and code are tied to the host as well. The cross-tenant check over HTTP with real tenants is in
 * packages/tenancy/tests/Integration/TenantIsolationTest (opt-in).
 */
class IsolationTest extends TestCase
{
    use RunsOidcLogin;
    use RunsSamlLogin;

    /** @return list<array{0:string,1:string}> */
    private function adminEndpoints(IdentityProvider $p): array
    {
        $base = '/api/admin/identity';

        return [
            ['GET', "$base/providers"],
            ['POST', "$base/providers"],
            ['GET', "$base/providers/{$p->id}"],
            ['PUT', "$base/providers/{$p->id}"],
            ['DELETE', "$base/providers/{$p->id}"],
            ['POST', "$base/providers/{$p->id}/metadata/refresh"],
            ['POST', "$base/providers/{$p->id}/sp-keys/rotate"],
            ['GET', "$base/audit"],
            ['GET', "$base/audit/verify"],
        ];
    }

    public function testGuestsGet401AndStudentsAndTutorsGet403OnEveryAdminEndpoint(): void
    {
        $provider = $this->provider();
        $student = $this->student();
        $tutor = $this->tutor();

        foreach ($this->adminEndpoints($provider) as [$method, $uri]) {
            $this->actingAs($student, 'api')->json($method, $uri)->assertStatus(403);
            $this->actingAs($tutor, 'api')->json($method, $uri)->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        foreach ($this->adminEndpoints($provider) as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }
        $this->assertNotNull(IdentityProvider::query()->find($provider->id));
        $this->assertSame(0, IdentityAudit::query()->count());
    }

    public function testTheTwoPermissionsAreSeparate(): void
    {
        $provider = $this->provider();
        $auditor = $this->student();
        $auditor->givePermissionTo('identity_audit_read');
        $manager = $this->student();
        $manager->givePermissionTo('identity_provider_manage');

        $this->actingAs($auditor, 'api')->getJson('/api/admin/identity/audit')->assertOk();
        $this->actingAs($auditor, 'api')->getJson('/api/admin/identity/providers')->assertStatus(403);
        $this->actingAs($manager, 'api')->getJson('/api/admin/identity/providers/' . $provider->id)->assertOk();
        $this->actingAs($manager, 'api')->getJson('/api/admin/identity/audit')->assertStatus(403);
    }

    public function testAnIdFromAnotherTenantIsAnUnknownId(): void
    {
        $admin = $this->admin();

        foreach (['01j9z8x3v7c5qk2m4n6p8r0t1w', '2f1c9e1e-3b7a-4c8e-9d3f-5a6b7c8d9e0f', 'nope'] as $id) {
            $this->actingAs($admin, 'api')->getJson("/api/admin/identity/providers/$id")->assertStatus(404);
            $this->actingAs($admin, 'api')->putJson("/api/admin/identity/providers/$id", ['name' => 'x'])->assertStatus(404);
            $this->actingAs($admin, 'api')->deleteJson("/api/admin/identity/providers/$id")->assertStatus(404);
        }
    }

    public function testPublicEndpointsOnlyShowWhatIsEnabledForThisTenant(): void
    {
        $this->provider(['slug' => 'on', 'name' => 'On']);
        $this->provider(['slug' => 'off', 'name' => 'Off', 'enabled' => false]);

        $list = $this->getJson('/api/identity/providers')->assertOk()->json('data');

        $this->assertSame([['slug' => 'on', 'name' => 'On', 'protocol' => 'oidc']], $list);
        $this->start('off')->assertStatus(404);
        $this->start('unknown-in-this-tenant')->assertStatus(404);
    }

    public function testASignInStartedOnOneHostCannotFinishOnAnother(): void
    {
        $this->provider();
        $auth = $this->queryOf((string) $this->start('acme', [], 'tenant-a.example.test')->headers->get('Location'));
        $this->assertSame('http://tenant-a.example.test/api/identity/oidc/acme/callback', $auth['redirect_uri']);

        $this->answerCallback('acme', $auth['state'], ['host' => 'tenant-b.example.test'])->assertStatus(400);
        $this->assertCount(0, $this->idp->requestsTo('/token'));
    }

    public function testACodeIssuedOnOneHostCannotBeRedeemedOnAnother(): void
    {
        $this->provider();
        $this->newVerifier();
        $result = $this->signIn([], ['start_host' => 'tenant-a.example.test', 'host' => 'tenant-a.example.test']);
        $code = $this->returned($result['response'])['code'];

        $this->exchange($code, null, 'tenant-b.example.test')->assertStatus(400);
        // burned by the attempt, so it is useless at its own tenant as well
        $this->exchange($code, null, 'tenant-a.example.test')->assertStatus(400);
    }

    public function testASuccessfulSignInAtOneHostWorksAtItsOwnHost(): void
    {
        $this->provider();
        $this->newVerifier();
        $result = $this->signIn([], ['start_host' => 'tenant-a.example.test', 'host' => 'tenant-a.example.test']);
        $code = $this->returned($result['response'])['code'];

        $this->exchange($code, null, 'tenant-a.example.test')->assertOk();
    }

    // --- SAML ---------------------------------------------------------------------------------------------------

    public function testSamlEndpointsOfAnotherTenantsProviderDoNotExist(): void
    {
        $this->samlProvider(['slug' => 'only-here']);

        // tenant B has no such provider: its database never saw the slug
        foreach (['GET' => ['metadata', 'slo?SAMLRequest=x'], 'POST' => ['acs']] as $method => $paths) {
            foreach ($paths as $path) {
                $uri = 'api/identity/saml/not-in-this-tenant/' . $path;
                $response = $method === 'GET' ? $this->get($uri) : $this->post($uri, ['SAMLResponse' => 'x', 'RelayState' => 'x']);
                $this->assertContains($response->getStatusCode(), [400, 404], $uri);
            }
        }
        $this->get('api/identity/saml/not-in-this-tenant/metadata')->assertStatus(404);
        $this->start('not-in-this-tenant')->assertStatus(404);
    }

    public function testASamlSignInStartedOnOneHostCannotFinishOnAnother(): void
    {
        $this->samlProvider();
        $this->newVerifier();
        $authn = $this->authnRequestOf($this->start('acme-saml', [], 'tenant-a.example.test'));
        $this->assertStringContainsString('http://tenant-a.example.test/api/identity/saml/acme-saml/acs', $authn['xml']);
        $saml = $this->responseFor($authn, [], 'acme-saml', 'tenant-a.example.test');

        // the state is bound to the host it was started on
        $this->postAcs($saml, $authn['relay'], 'acme-saml', 'tenant-b.example.test')->assertStatus(400);
        $this->assertSame(0, \Ulams\Auth\Models\User::query()->where('email', 'ada@example.com')->count());
        $this->assertSame(0, IdentityAudit::query()->where('action', 'sso.login')->count());
    }

    public function testAResponseMadeForOneTenantsServiceProviderFailsAtAnother(): void
    {
        $this->samlProvider();
        $this->newVerifier();
        // tenant B's own sign-in, answered with an assertion the IdP made for tenant A (audience, destination, recipient of A)
        $authnB = $this->authnRequestOf($this->start('acme-saml', [], 'tenant-b.example.test'));
        $forA = $this->samlIdp->response([
            'acs' => $this->acsUrl('acme-saml', 'tenant-a.example.test'),
            'sp' => $this->spEntityId('acme-saml', 'tenant-a.example.test'),
            'request_id' => $authnB['id'],
        ]);

        $params = $this->returned($this->postAcs($forA, $authnB['relay'], 'acme-saml', 'tenant-b.example.test'));

        $this->assertSame('provider_failed', $params['error'] ?? null);
        $this->assertContains($this->lastFailure(), ['audience_mismatch', 'destination_mismatch']);
        $this->assertArrayNotHasKey('code', $params);
    }

    public function testASamlMetadataDocumentNamesTheHostItWasFetchedFrom(): void
    {
        $this->samlProvider();

        $a = $this->get('http://tenant-a.example.test/api/identity/saml/acme-saml/metadata')->assertOk()->getContent();
        $b = $this->get('http://tenant-b.example.test/api/identity/saml/acme-saml/metadata')->assertOk()->getContent();

        $this->assertStringContainsString('entityID="http://tenant-a.example.test/api/identity/saml/acme-saml/metadata"', $a);
        $this->assertStringNotContainsString('tenant-b', $a);
        $this->assertStringContainsString('entityID="http://tenant-b.example.test/api/identity/saml/acme-saml/metadata"', $b);
    }

    public function testASamlLogoutRequestForAnotherHostIsRefused(): void
    {
        $this->samlProvider();
        $message = $this->samlIdp->logoutMessage(['destination' => 'http://tenant-a.example.test/api/identity/saml/acme-saml/slo', 'name_id' => 'user-1']);

        $this->get('http://tenant-b.example.test/api/identity/saml/acme-saml/slo?' . $message['query'])->assertStatus(400);
        $this->get('http://tenant-a.example.test/api/identity/saml/acme-saml/slo?' . $message['query'])->assertRedirect();
    }
}
