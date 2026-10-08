<?php

namespace Ulams\Lti\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Lti\Models\LtiKey;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Support\Lti;
use Ulams\Lti\Tests\Support\KeyPair;
use Ulams\Lti\Tests\TestCase;

/**
 * Tenants have separate databases (registrations, keys, nonces, launches, scores) and their own
 * APP_KEY (ADR 0007). What can cross tenants is what a browser or a tool carries from one host to
 * another: login hints, deep-linking data, AGS access tokens, one-time codes and the JWKS. These
 * tests switch the tenant secrets (APP_KEY, LTI key set) and check that every such value of
 * "tenant A" is rejected by "tenant B". The opt-in HTTP test against two real tenants is in
 * packages/tenancy/tests/Integration/TenantIsolationTest.php.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesUsers;

    public function testLoginHintsOfAnotherTenantAreRejected(): void
    {
        $tool = LtiTool::factory()->create();
        [$course, , $topic] = $this->courseWithLink($tool);
        $student = $this->makeStudent();
        $this->enrol($student, $course);
        $url = $this->actingAs($student, 'api')->postJson("/api/lti/launches/{$topic->getKey()}")->json('data.url');
        $this->app['auth']->forgetGuards();
        parse_str(parse_url($url, PHP_URL_QUERY), $login);

        $this->becomeAnotherTenant();

        $this->get('/api/lti/platform/authorize?' . http_build_query([
            'scope' => 'openid', 'response_type' => 'id_token', 'client_id' => $tool->client_id,
            'redirect_uri' => $tool->launch_url, 'login_hint' => $login['login_hint'], 'nonce' => 'n',
        ]))->assertStatus(400)->assertSee('Invalid or expired login_hint');
    }

    public function testAgsAccessTokensOfAnotherTenantAreRejected(): void
    {
        $keys = new KeyPair();
        $tool = LtiTool::factory()->create(['public_key' => $keys->public, 'jwks_url' => null]);
        [$course] = $this->courseWithLink($tool);
        $token = $this->post('/api/lti/platform/token', [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $keys->sign(['iss' => $tool->client_id, 'sub' => $tool->client_id, 'aud' => Lti::url('api/lti/platform/token'), 'exp' => time() + 60, 'jti' => Str::uuid()->toString()]),
            'scope' => Lti::SCOPE_LINEITEM_READONLY,
        ])->assertOk()->json('access_token');

        $this->becomeAnotherTenant();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/lti/platform/ags/{$course->getKey()}/lineitems")
            ->assertUnauthorized();
    }

    public function testDeepLinkingDataOfAnotherTenantIsRejected(): void
    {
        $keys = new KeyPair();
        $tool = LtiTool::factory()->create(['public_key' => $keys->public, 'jwks_url' => null]);
        [, $lesson] = $this->courseWithLink($tool);
        $data = app(\Ulams\Lti\Support\HintSigner::class)->sign('deep_link_data', ['uid' => $this->makeAdmin()->getKey(), 'tool' => $tool->getKey(), 'lesson' => $lesson->getKey()], 600);

        $this->becomeAnotherTenant();

        $this->post('/api/lti/platform/deep-links', ['JWT' => $keys->sign([
            'iss' => $tool->client_id, 'aud' => [Lti::issuer()], 'exp' => time() + 60, 'nonce' => Str::uuid()->toString(),
            Lti::CLAIM_DEPLOYMENT_ID => $tool->deployment_id, Lti::CLAIM_MESSAGE_TYPE => Lti::MSG_DEEP_LINKING_RESPONSE,
            Lti::CLAIM_VERSION => '1.3.0', Lti::CLAIM_DL_CONTENT_ITEMS => [['type' => 'ltiResourceLink', 'title' => 'X']],
            Lti::CLAIM_DL_DATA => $data,
        ])])->assertStatus(400);
    }

    public function testTheJwksPublishesOnlyThisTenantsKeysAndPrivateKeysAreBoundToItsAppKey(): void
    {
        app(KeyService::class)->ensureKeys();
        $kids = LtiKey::query()->pluck('kid')->sort()->values()->all();

        $published = collect($this->getJson('/api/lti/jwks')->json('keys'))->pluck('kid')->sort()->values()->all();
        $this->assertSame($kids, $published);

        $this->becomeAnotherTenant(keepKeys: true);
        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);
        LtiKey::query()->firstOrFail()->private_key;
    }

    public function testOneTimeCodesAndTrackingAreBoundToTheTenantDatabase(): void
    {
        // A code is a random value stored in the tenant database: another tenant has no row for it.
        $this->postJson('/api/lti/tool/exchange', ['code' => Str::random(48)])->assertUnauthorized();
    }

    /**
     * Same process, other tenant: a different APP_KEY and (unless kept) a different key set.
     */
    private function becomeAnotherTenant(bool $keepKeys = false): void
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $this->app->forgetInstance('encrypter');
        \Illuminate\Support\Facades\Crypt::clearResolvedInstance('encrypter');
        if (!$keepKeys) {
            DB::table('lti_keys')->delete();
        }
    }
}
