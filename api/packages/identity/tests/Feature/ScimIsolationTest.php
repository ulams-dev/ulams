<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Tests\Support\RunsScim;
use Ulams\Identity\Tests\TestCase;

/**
 * Access control and tenant isolation of every SCIM route. A tenant is one database chosen by host, so a user, group,
 * token or provider of tenant A does not exist at tenant B; what is proven here is that inside one tenant a token
 * reaches exactly its own provider's resources, that no other kind of credential reaches the routes, and that the
 * routes are all covered (a new SCIM route must be added to this list or the test fails).
 */
class ScimIsolationTest extends TestCase
{
    use RunsScim;

    /** @return list<array{0:string,1:string,2:?array}> */
    private function routes(string $userId, ?string $groupId = null): array
    {
        $routes = [
            ['GET', '/ServiceProviderConfig', null], ['GET', '/ResourceTypes', null], ['GET', '/ResourceTypes/User', null],
            ['GET', '/Schemas', null], ['GET', '/Schemas/' . Schemas::USER, null],
            ['GET', '/Users', null], ['POST', '/Users', $this->userBody('new@example.com')], ['POST', '/Users/.search', ['schemas' => [Schemas::SEARCH]]],
            ['GET', "/Users/$userId", null], ['PUT', "/Users/$userId", $this->userBody()],
            ['PATCH', "/Users/$userId", $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => false]])], ['DELETE', "/Users/$userId", null],
        ];
        if ($groupId !== null) {
            array_push(
                $routes,
                ['GET', '/Groups', null], ['POST', '/Groups', ['schemas' => [Schemas::GROUP], 'displayName' => 'G']], ['POST', '/Groups/.search', ['schemas' => [Schemas::SEARCH]]],
                ['GET', "/Groups/$groupId", null], ['PUT', "/Groups/$groupId", ['schemas' => [Schemas::GROUP], 'displayName' => 'G']],
                ['PATCH', "/Groups/$groupId", $this->patchBody([['op' => 'replace', 'path' => 'displayName', 'value' => 'H']])], ['DELETE', "/Groups/$groupId", null],
            );
        }

        return $routes;
    }

    public function testEveryRegisteredScimRouteIsExercisedHere(): void
    {
        $registered = [];
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/scim/v2')) {
                foreach ($route->methods() as $method) {
                    if ($method !== 'HEAD') {
                        $registered[] = $method . ' ' . preg_replace('#/\{[^}]+\}#', '/{x}', substr($route->uri(), strlen('api/scim/v2')));
                    }
                }
            }
        }
        $covered = [];
        foreach ($this->routes('1', '1') as [$method, $path]) {
            $covered[] = $method . ' ' . preg_replace('#/(?:\d+|User|urn:.*)$#', '/{x}', $path);
        }
        $normalise = fn (array $list) => collect($list)->map(fn ($r) => preg_replace('#/\{x\}$#', '/{x}', $r))->unique()->sort()->values()->all();

        $this->assertSame($normalise($registered), $normalise($covered));
    }

    public function testNoCredentialMeansNoAccessToAnyRoute(): void
    {
        $provider = $this->scimProvider();
        $this->scimToken($provider);
        $user = $this->createScimUser();
        $group = $this->createScimGroup('Nurses', [$user['id']]);

        foreach ($this->routes($user['id'], $group['id']) as [$method, $path, $body]) {
            $this->scim($method, $path, $body, [], 'ulams_pat_garbage')->assertStatus(401);
            $this->app['auth']->forgetGuards();
            $response = $this->call($method, '/api/scim/v2' . $path, [], [], [], ['CONTENT_TYPE' => 'application/scim+json'], $body === null ? null : json_encode($body));
            $this->assertSame(401, $response->getStatusCode(), "$method $path");
            $this->assertSame('401', $response->json('status'), "$method $path");
        }
        $this->assertSame('ada@example.com', \Ulams\Auth\Models\User::query()->findOrFail($user['id'])->email);
        $this->assertTrue(\Ulams\Auth\Models\User::query()->findOrFail($user['id'])->is_active);
        $this->assertSame('Nurses', \Ulams\Auth\Models\Group::query()->findOrFail($group['id'])->name);
        $this->assertSame(2, ExternalId::query()->count());
    }

    public function testNoOtherKindOfTokenReachesAnyRoute(): void
    {
        $provider = $this->scimProvider();
        $this->scimToken($provider);
        $user = $this->createScimUser();
        $group = $this->createScimGroup('Nurses', [$user['id']]);
        $admin = $this->admin();
        $service = app(PersonalAccessTokenServiceContract::class);
        $tokens = [
            'cli' => $service->issue($admin, 'a', ['*'], 30, 'cli')->secret,
            'ci' => $service->issue($admin, 'b', ['users:write', 'identity:write'], 30, 'ci')->secret,
            'agent' => $service->issue($admin, 'c', ['*'], 30, 'agent', 'bot')->secret,
            'login' => app(\Ulams\Auth\Services\AuthService::class)->createTokenForUser($admin)->accessToken,
        ];

        foreach ($tokens as $kind => $secret) {
            foreach ($this->routes($user['id'], $group['id']) as [$method, $path, $body]) {
                $this->scim($method, $path, $body, [], $secret)->assertStatus(403);
            }
        }
        $this->assertSame(2, ExternalId::query()->count());
        $this->assertSame(0, IdentityAudit::query()->where('action', 'scim.user_deactivated')->count());
    }

    public function testATokenReachesOnlyItsOwnProvidersResources(): void
    {
        $a = $this->scimProvider();
        $b = $this->provider(['slug' => 'other', 'name' => 'Other']);
        $tokenA = $this->scimToken($a);
        $tokenB = $this->scimToken($b);
        $user = $this->scim('POST', '/Users', $this->userBody(), [], $tokenA)->assertStatus(201)->json();
        $group = $this->scim('POST', '/Groups', $this->groupBody('Nurses', [$user['id']]), [], $tokenA)->assertStatus(201)->json();

        foreach ([['GET', "/Groups/{$group['id']}", null], ['PUT', "/Groups/{$group['id']}", $this->groupBody('Hacked')], ['PATCH', "/Groups/{$group['id']}", $this->patchBody([['op' => 'replace', 'path' => 'displayName', 'value' => 'Hacked']])], ['DELETE', "/Groups/{$group['id']}", null]] as [$method, $path, $body]) {
            $this->scim($method, $path, $body, [], $tokenB)->assertStatus(404);
        }
        $this->assertSame('Nurses', \Ulams\Auth\Models\Group::query()->findOrFail($group['id'])->name);
        $this->assertSame(0, $this->scim('GET', '/Groups', null, [], $tokenB)->json('totalResults'));
        // a group of provider B cannot take a user of provider A as a member
        $this->scim('POST', '/Groups', $this->groupBody('Spies', [$user['id']]), [], $tokenB)->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');

        foreach ([['GET', "/Users/{$user['id']}", null], ['PUT', "/Users/{$user['id']}", $this->userBody()], ['PATCH', "/Users/{$user['id']}", $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => false]])], ['DELETE', "/Users/{$user['id']}", null]] as [$method, $path, $body]) {
            $this->scim($method, $path, $body, [], $tokenB)->assertStatus(404);
        }
        $this->assertTrue(\Ulams\Auth\Models\User::query()->findOrFail($user['id'])->is_active);
        $this->assertNull(ExternalId::query()->where('provider_id', $a->id)->firstOrFail()->deleted_at);
        $this->assertSame(0, $this->scim('GET', '/Users', null, [], $tokenB)->json('totalResults'));
        $this->assertSame(1, $this->scim('GET', '/Users', null, [], $tokenA)->json('totalResults'));
    }

    public function testTheServiceUserIsNotInTheAdminUserList(): void
    {
        $provider = $this->scimProvider();
        $this->scimToken($provider);
        $admin = $this->admin();
        $student = $this->student(['email' => 'visible@example.com']);

        $list = $this->actingAs($admin, 'api')->getJson('/api/admin/users?per_page=100')->assertOk();

        $emails = array_column($list->json('data'), 'email');
        $this->assertContains('visible@example.com', $emails);
        $this->assertNotContains('scim+acme@identity.invalid', $emails);
    }
}
