<?php

namespace Ulams\ExamplePlugin\Tests\Api;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\ExamplePlugin\Events\GreetingSent;
use Ulams\ExamplePlugin\Tests\TestCase;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token
 * issued by one tenant must be rejected by another. Both "tenants" share this process and
 * test database here; switching the key pair is what tells them apart (the same technique
 * as packages/demo/tests/Api/DemoApiTest.php).
 */
class TenantIsolationTest extends TestCase
{
    public function testAnAdminTokenOfAnotherTenantIsRejected(): void
    {
        Event::fake([GreetingSent::class]);
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $tokenA = $admin->createToken('test')->accessToken;

        $this->send($tokenA, $student->getKey())->assertOk();

        $this->switchToTenantWithOwnKeys();

        $this->send($tokenA, $student->getKey())->assertUnauthorized();
        Event::assertDispatchedTimes(GreetingSent::class, 1);

        $tokenB = $admin->createToken('test')->accessToken;
        $this->send($tokenB, $student->getKey())->assertOk();
    }

    private function send(string $token, int $userId)
    {
        $response = $this->postJson('/api/admin/example-plugin/greetings', ['user_id' => $userId], ['Authorization' => 'Bearer ' . $token]);
        // the next request must authenticate from its own header only
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
