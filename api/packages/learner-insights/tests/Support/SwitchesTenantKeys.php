<?php

namespace Ulams\LearnerInsights\Tests\Support;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by
 * one tenant must be rejected by another. Both "tenants" share this process and test database here;
 * switching the key pair is what tells them apart (the technique of
 * packages/example-plugin/tests/Api/TenantIsolationTest.php).
 */
trait SwitchesTenantKeys
{
    protected function switchToTenantWithOwnKeys(): void
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

    /** One request that authenticates from its own header only (the guard is reset afterwards). */
    protected function sendAs(string $token, string $method, string $uri, array $body = [])
    {
        $response = $this->json($method, $uri, $body, ['Authorization' => 'Bearer ' . $token]);
        Auth::forgetGuards();

        return $response;
    }
}
