<?php

namespace Ulams\Core\Tests\Concerns;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by
 * one tenant must be rejected by every other tenant. A test process is one tenant at a time; to play
 * "the other tenant", swap the key pair the resource server verifies with: a token issued before the
 * swap was signed by tenant A's key and is now presented to tenant B (ADR 0190).
 *
 * Usage: issue the token first, call {@see switchToTenantWithOwnKeys()}, then send the request with
 * {@see sendAs()}.
 */
trait ForeignTenantToken
{
    /** From here on this process verifies tokens with a fresh key pair: tokens issued so far are foreign. */
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

    /**
     * One request that authenticates from its own header only (a null token sends none): the guard is
     * reset afterwards, so the next request does not inherit the user.
     */
    protected function sendAs(?string $token, string $method, string $uri, array $body = [])
    {
        $headers = $token === null ? [] : ['Authorization' => 'Bearer ' . $token];
        $response = $this->json($method, $uri, $body, $headers);
        Auth::forgetGuards();

        return $response;
    }
}
