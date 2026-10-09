<?php

namespace Ulams\Demo\Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Checks demo mode against the running docker stack: two demo tenants log in without a
 * password, and a demo token of one tenant is rejected by the other. Needs two tenants with
 * DEMO_MODE=true and the demo seed (README "Demo mode"). Opt-in:
 *
 *   docker compose exec -T -e DEMO_INTEGRATION=1 api \
 *     vendor/bin/phpunit packages/demo/tests/Integration
 *
 * DEMO_INTEGRATION_URL (default http://caddy) is where requests go; DEMO_INTEGRATION_HOSTS
 * (default coffee.localhost,oncall.localhost) names the two tenants.
 */
class DemoTenantIsolationTest extends TestCase
{
    private Client $http;
    private string $a;
    private string $b;

    protected function setUp(): void
    {
        if (!getenv('DEMO_INTEGRATION')) {
            $this->markTestSkipped('Set DEMO_INTEGRATION=1 to test the running demo tenants (see class docblock).');
        }

        [$this->a, $this->b] = array_map('trim', explode(',', getenv('DEMO_INTEGRATION_HOSTS') ?: 'coffee.localhost,oncall.localhost'));
        $this->http = new Client([
            'base_uri' => getenv('DEMO_INTEGRATION_URL') ?: 'http://caddy',
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    public function testDemoLoginWorksOnEachTenantAndTokensDoNotCross(): void
    {
        foreach (['student', 'admin'] as $role) {
            $tokenA = $this->login($this->a, $role);
            $tokenB = $this->login($this->b, $role);

            $me = $this->request($this->a, 'GET', '/api/profile/me', $tokenA);
            $this->assertSame(200, $me->getStatusCode());
            $this->assertContains($role, json_decode((string) $me->getBody(), true)['data']['roles']);

            $this->assertSame(401, $this->request($this->b, 'GET', '/api/profile/me', $tokenA)->getStatusCode());
            $this->assertSame(401, $this->request($this->a, 'GET', '/api/profile/me', $tokenB)->getStatusCode());
        }
    }

    private function login(string $host, string $role): string
    {
        $response = $this->request($host, 'POST', '/api/demo/login', null, ['role' => $role]);
        $this->assertSame(200, $response->getStatusCode(), "demo login as {$role} on {$host}: " . $response->getBody());

        return json_decode((string) $response->getBody(), true)['data']['token'];
    }

    private function request(string $host, string $method, string $uri, ?string $token = null, ?array $json = null): ResponseInterface
    {
        $options = ['headers' => array_filter([
            'Host' => $host,
            'Accept' => 'application/json',
            'Authorization' => $token ? 'Bearer ' . $token : null,
        ])];
        if ($json !== null) {
            $options['json'] = $json;
        }

        return $this->http->request($method, $uri, $options);
    }
}
