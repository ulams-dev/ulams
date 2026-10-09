<?php

namespace Ulams\Adapt\Tests\Api;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Adapt\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so an admin token
 * issued by one tenant must be rejected by another. Same technique as
 * packages/example-plugin/tests/Api/TenantIsolationTest.php.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_adapt.enabled' => true]);
    }

    public function testAnAdminTokenOfAnotherTenantIsRejected(): void
    {
        $token = $this->makeAdmin()->createToken('test')->accessToken;

        $this->list($token)->assertOk();

        $this->switchToTenantWithOwnKeys();

        $this->list($token)->assertUnauthorized();
        $this->versions($token)->assertUnauthorized();
    }

    private function list(string $token)
    {
        $response = $this->getJson('/api/admin/adapt', ['Authorization' => 'Bearer ' . $token]);
        Auth::forgetGuards();

        return $response;
    }

    private function versions(string $token)
    {
        $response = $this->getJson('/api/admin/adapt/1/versions', ['Authorization' => 'Bearer ' . $token]);
        Auth::forgetGuards();

        return $response;
    }

    private function switchToTenantWithOwnKeys(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];

        config(['passport.private_key' => $private, 'passport.public_key' => $public]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();
    }
}
