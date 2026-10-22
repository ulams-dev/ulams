<?php

namespace Ulams\Identity\Tests\Feature;

use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
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
}
