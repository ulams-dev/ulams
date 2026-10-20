<?php

namespace Ulams\Webhooks\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Core\Http\SafeHttp;
use Ulams\Webhooks\Models\WebhookEndpoint;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Webhooks\Tests\TestCase;

class EndpointApiTest extends TestCase
{
    use CreatesUsers;

    private const BASE = '/api/admin/webhooks/endpoints';

    public function testRequiresAuthenticationAndThePermission(): void
    {
        $this->getJson(self::BASE)->assertUnauthorized();

        $student = $this->makeStudent();
        $this->actingAs($student, 'api')->getJson(self::BASE)->assertForbidden();
        $this->actingAs($student, 'api')->postJson(self::BASE, ['url' => 'https://hooks.example.com/x', 'events' => ['*']])->assertForbidden();
        $this->actingAs($student, 'api')->getJson('/api/admin/webhooks/deliveries')->assertForbidden();
        $this->actingAs($student, 'api')->getJson('/api/admin/webhooks/events')->assertForbidden();
        $this->assertSame(0, WebhookEndpoint::query()->count());
    }

    public function testTheSecretIsShownOnceAndStoredEncrypted(): void
    {
        $admin = $this->makeAdmin();
        $created = $this->actingAs($admin, 'api')->postJson(self::BASE, [
            'url' => 'https://hooks.example.com/ulams', 'description' => 'CRM', 'events' => ['course.completed', 'enrolment.created'],
        ])->assertCreated();
        $secret = $created->json('data.secret');
        $id = $created->json('data.id');

        $this->assertStringStartsWith('whsec_', $secret);
        $created->assertJsonPath('data.enabled', true)->assertJsonPath('data.api_version', '2026-10-20')->assertJsonPath('data.secret_hint', substr($secret, -4));
        $this->assertNotSame($secret, DB::table('webhook_endpoints')->where('id', $id)->value('secret'), 'stored in clear text');
        $this->assertSame($secret, WebhookEndpoint::query()->findOrFail($id)->secret);

        $this->actingAs($admin, 'api')->getJson(self::BASE . '/' . $id)->assertOk()->assertJsonMissingPath('data.secret');
        $this->actingAs($admin, 'api')->getJson(self::BASE)->assertOk()->assertJsonMissingPath('data.0.secret');
        $this->assertStringNotContainsString($secret, $this->actingAs($admin, 'api')->getJson(self::BASE)->getContent());
    }

    public function testRotatingTheSecretReturnsTheNewOneOnceAndReplacesTheOldOne(): void
    {
        $admin = $this->makeAdmin();
        [$endpoint, $old] = $this->endpoint();

        $res = $this->actingAs($admin, 'api')->postJson(self::BASE . '/' . $endpoint->id . '/rotate-secret')->assertOk();
        $new = $res->json('data.secret');

        $this->assertNotSame($old, $new);
        $this->assertSame($new, $endpoint->refresh()->secret);
        $this->assertSame(substr($new, -4), $endpoint->secret_hint);
    }

    public function testUrlsThatCouldReachTheInternalNetworkAreRefused(): void
    {
        $admin = $this->makeAdmin();
        $refused = [
            'http://hooks.example.com/x',
            'https://127.0.0.1/x',
            'https://localhost:8443/x',
            'https://169.254.169.254/latest/meta-data/',
            'https://10.0.0.5/x',
            'https://[::1]/x',
            'https://user:pass@hooks.example.com/x',
            'ftp://hooks.example.com/x',
            'not a url',
        ];
        SafeHttp::useResolver(fn (string $host) => $host === 'localhost' ? ['127.0.0.1'] : ['93.184.216.34']);
        foreach ($refused as $url) {
            $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => $url, 'events' => ['*']])->assertStatus(422);
        }
        // a public name that resolves to a private address (the DNS rebinding setup)
        SafeHttp::useResolver(fn (string $host) => ['93.184.216.34', '10.1.2.3']);
        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => 'https://rebind.example.com/x', 'events' => ['*']])->assertStatus(422)->assertJsonValidationErrors('url');
        $this->assertSame(0, WebhookEndpoint::query()->count());
    }

    public function testInsecureTargetsAreAllowedOnlyWhenTheOperatorOptsIn(): void
    {
        $admin = $this->makeAdmin();
        config(['ulams_webhooks.allow_insecure' => true]);

        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => 'http://localhost:3000/hook', 'events' => ['*']])->assertCreated();
    }

    public function testTheHostAllowListRestrictsTargets(): void
    {
        $admin = $this->makeAdmin();
        config(['ulams_webhooks.allowed_hosts' => ['hooks.example.com']]);

        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => 'https://other.example.org/x', 'events' => ['*']])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => 'https://hooks.example.com/x', 'events' => ['*']])->assertCreated();
    }

    public function testEventsMustBeKnownAndAreNormalised(): void
    {
        $admin = $this->makeAdmin();
        $url = 'https://hooks.example.com/x';

        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => $url, 'events' => []])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => $url, 'events' => ['nope.created']])->assertStatus(422);
        // planned events cannot be subscribed to yet
        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => $url, 'events' => ['order.paid']])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => $url, 'events' => ['course.completed', 'course.completed', '*']])
            ->assertCreated()->assertJsonPath('data.events', ['*']);
    }

    public function testTheEndpointLimitIsEnforced(): void
    {
        $admin = $this->makeAdmin();
        config(['ulams_webhooks.max_endpoints' => 1]);
        $this->endpoint();

        $this->actingAs($admin, 'api')->postJson(self::BASE, ['url' => 'https://hooks.example.com/two', 'events' => ['*']])->assertStatus(422);
    }

    public function testUpdateChangesFieldsAndReEnablingClearsTheFailureState(): void
    {
        $admin = $this->makeAdmin();
        [$endpoint] = $this->endpoint();
        $endpoint->forceFill(['enabled' => false, 'disabled_reason' => 'Disabled after 25 failed deliveries in a row.', 'consecutive_failures' => 25])->save();

        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $endpoint->id, ['enabled' => true, 'events' => ['course.completed'], 'url' => 'https://hooks.example.com/v2'])
            ->assertOk()->assertJsonPath('data.enabled', true)->assertJsonPath('data.disabled_reason', null)->assertJsonPath('data.consecutive_failures', 0)
            ->assertJsonPath('data.events', ['course.completed'])->assertJsonPath('data.url', 'https://hooks.example.com/v2');

        $this->actingAs($admin, 'api')->putJson(self::BASE . '/' . $endpoint->id, ['url' => 'https://127.0.0.1/x'])->assertStatus(422);
        $this->assertSame('https://hooks.example.com/v2', $endpoint->refresh()->url);
    }

    public function testDeleteRemovesTheEndpointAndItsLog(): void
    {
        $admin = $this->makeAdmin();
        [$endpoint] = $this->endpoint();

        $this->actingAs($admin, 'api')->deleteJson(self::BASE . '/' . $endpoint->id)->assertOk();

        $this->assertSame(0, WebhookEndpoint::query()->count());
        $this->actingAs($admin, 'api')->getJson(self::BASE . '/' . $endpoint->id)->assertNotFound();
    }

    public function testTheEventCatalogueSaysWhatIsWiredAndWhatIsPlanned(): void
    {
        $admin = $this->makeAdmin();
        $types = collect($this->actingAs($admin, 'api')->getJson('/api/admin/webhooks/events')->assertOk()->json('data'))->pluck('status', 'type');

        $this->assertSame('available', $types['course.completed']);
        $this->assertSame('available', $types['enrolment.created']);
        $this->assertSame('available', $types['update_proposal.applied']);
        $this->assertSame('available', $types['certificate.issued']);
        $this->assertSame('planned', $types['order.paid']);
    }

    public function testAnEndpointOfAnotherTenantDoesNotResolveAndItsTokenIsRejected(): void
    {
        // Tenants have separate databases and their own Passport key pair (ADR 0007). Both "tenants" share this
        // process here; switching the key pair tells them apart (same technique as packages/example-plugin).
        $admin = $this->makeAdmin();
        $tokenA = $admin->createToken('test')->accessToken;
        $this->getJson(self::BASE, ['Authorization' => 'Bearer ' . $tokenA])->assertOk();
        Auth::forgetGuards();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        $this->getJson(self::BASE, ['Authorization' => 'Bearer ' . $tokenA])->assertUnauthorized();
        // an id that exists in no database of this tenant is a 404, never someone else's row
        $this->actingAs($admin, 'api')->getJson(self::BASE . '/01j9z8x3v7c5qk2m4n6p8r0t1w')->assertNotFound();
    }

    public function testTheManagementApiIsRateLimited(): void
    {
        $admin = $this->makeAdmin();
        config(['ulams_webhooks.api_rate_limit' => 2]);

        $this->actingAs($admin, 'api')->getJson(self::BASE)->assertOk();
        $this->actingAs($admin, 'api')->getJson(self::BASE)->assertOk();
        $this->actingAs($admin, 'api')->getJson(self::BASE)->assertStatus(429);
    }
}
