<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\PolicyLifecycle\Tests\TestCase;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by one tenant must be
 * rejected by another on every policy endpoint. The route-walking test of ADR 0190 covers every route; this one also
 * proves the policy endpoints really answer for the right tenant first.
 */
class TenantIsolationTest extends TestCase
{
    public function testATokenOfAnotherTenantIsRejectedEverywhere(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('test')->accessToken;
        $id = $this->createPolicy($admin);
        $endpoints = [
            ['GET', '/api/admin/policies', []],
            ['POST', '/api/admin/policies', ['title' => 'x']],
            ['GET', "/api/admin/policies/{$id}", []],
            ['GET', "/api/admin/policies/{$id}/versions", []],
            ['GET', "/api/admin/policies/{$id}/variants", []],
            ['POST', "/api/admin/policies/{$id}/variants/generate", []],
            ['POST', "/api/admin/policies/{$id}/versions", []],
            ['GET', "/api/admin/policies/{$id}/versions/1", []],
            ['POST', "/api/admin/policies/{$id}/versions/1/approve", []],
            ['POST', "/api/admin/policies/{$id}/versions/1/reject", ['reason' => 'No']],
            ['GET', "/api/admin/policies/{$id}/dashboard", []],
            ['GET', "/api/admin/policies/{$id}/dashboard/export", []],
        ];

        $this->call_($token, 'GET', "/api/admin/policies/{$id}")->assertOk();
        $this->switchToTenantWithOwnKeys();

        foreach ($endpoints as [$method, $url, $body]) {
            $this->call_($token, $method, $url, $body)->assertUnauthorized();
        }
    }

    private function call_(string $token, string $method, string $url, array $body = [])
    {
        $response = $this->json($method, $url, $body, ['Authorization' => 'Bearer ' . $token]);
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
