<?php

namespace Ulams\Tenancy\Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * End-to-end isolation check against the running docker stack: provisions two real tenants
 * with `ulams:tenant:create` (database, bucket, env file, keys), then talks to them over HTTP
 * through Caddy.
 *
 * Opt-in, because it creates and drops real databases and buckets:
 *
 *   docker compose exec -T -e TENANCY_INTEGRATION=1 api \
 *     vendor/bin/phpunit packages/tenancy/tests/Integration
 *
 * TENANCY_INTEGRATION_URL (default http://caddy) is where requests go; the tenant is chosen
 * with the Host header. TENANCY_INTEGRATION_KEEP=1 keeps the tenants afterwards.
 */
class TenantIsolationTest extends TestCase
{
    private const A = 'isoprobea';
    private const B = 'isoprobeb';

    private static bool $provisioned = false;
    private static Client $http;

    public static function setUpBeforeClass(): void
    {
        if (!getenv('TENANCY_INTEGRATION')) {
            return;
        }

        self::$http = new Client([
            'base_uri' => getenv('TENANCY_INTEGRATION_URL') ?: 'http://caddy',
            'http_errors' => false,
            'timeout' => 60,
        ]);
        foreach ([self::A, self::B] as $slug) {
            self::artisan(['ulams:tenant:create', $slug, '--name=Isolation ' . strtoupper(substr($slug, -1)), '--users=1']);
        }
        self::$provisioned = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$provisioned || getenv('TENANCY_INTEGRATION_KEEP')) {
            return;
        }
        foreach ([self::A, self::B] as $slug) {
            self::artisan(['ulams:tenant:delete', $slug, '--force']);
        }
    }

    protected function setUp(): void
    {
        if (!getenv('TENANCY_INTEGRATION')) {
            $this->markTestSkipped('Set TENANCY_INTEGRATION=1 to provision real tenants (see class docblock).');
        }
    }

    public function testTenantsAreServedOnTheirOwnHosts(): void
    {
        $this->assertSame('Application Name: Isolation A', (string) $this->request(self::A, 'GET', '/api/name')->getBody());
        $this->assertSame('Application Name: Isolation B', (string) $this->request(self::B, 'GET', '/api/name')->getBody());
        $this->assertSame(404, $this->request('isoprobenope', 'GET', '/api/name')->getStatusCode());
    }

    public function testDataAndTokensDoNotCrossTenants(): void
    {
        $tokenA = $this->login(self::A);
        $tokenB = $this->login(self::B);
        $title = 'Isolation probe ' . bin2hex(random_bytes(4));

        $created = $this->request(self::A, 'POST', '/api/admin/courses', $tokenA, [
            'title' => $title,
            'status' => 'published',
        ]);
        $this->assertContains($created->getStatusCode(), [200, 201], (string) $created->getBody());

        $this->assertContains($title, $this->courseTitles(self::A));
        $this->assertNotContains($title, $this->courseTitles(self::B));

        // every tenant signs tokens with its own Passport keys
        $this->assertSame(200, $this->request(self::A, 'GET', '/api/profile/me', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/profile/me', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/admin/courses', $tokenA)->getStatusCode());
        $this->assertSame(401, $this->request(self::A, 'GET', '/api/profile/me', $tokenB)->getStatusCode());
        $this->assertSame(401, $this->request('api.localhost', 'GET', '/api/profile/me', $tokenA)->getStatusCode());

        // users of one tenant cannot log in to another
        $wrong = $this->request(self::B, 'POST', '/api/auth/login', null, [
            'email' => 'admin@' . self::A . '.ulams.app',
            'password' => getenv('TENANT_DEMO_PASSWORD') ?: 'secret',
        ]);
        $this->assertNotSame(200, $wrong->getStatusCode());
    }

    public function testLtiKeySetsAndRegistrationsDoNotCrossTenants(): void
    {
        $kids = fn (string $slug) => array_column(json_decode((string) $this->request($slug, 'GET', '/api/lti/jwks')->getBody(), true)['keys'] ?? [], 'kid');
        $a = $kids(self::A);
        $b = $kids(self::B);
        $this->assertNotEmpty($a);
        $this->assertNotEmpty($b);
        $this->assertSame([], array_intersect($a, $b), 'every tenant signs LTI messages with its own keys');

        $tokenA = $this->login(self::A);
        $tool = $this->request(self::A, 'POST', '/api/admin/lti/tools', $tokenA, [
            'name' => 'Isolation tool',
            'oidc_login_url' => 'https://tool.example.test/login',
            'launch_url' => 'https://tool.example.test/launch',
            'jwks_url' => 'https://tool.example.test/jwks',
        ]);
        $this->assertSame(201, $tool->getStatusCode(), (string) $tool->getBody());
        $clientId = json_decode((string) $tool->getBody(), true)['data']['client_id'];

        // tenant B does not know tenant A's tool, and A's admin token is useless on B
        $authorize = $this->request(self::B, 'GET', '/api/lti/platform/authorize?' . http_build_query([
            'scope' => 'openid', 'response_type' => 'id_token', 'client_id' => $clientId,
            'redirect_uri' => 'https://tool.example.test/launch', 'login_hint' => 'x', 'nonce' => 'n',
        ]));
        $this->assertSame(401, $authorize->getStatusCode());
        $this->assertSame(401, $this->request(self::B, 'GET', '/api/admin/lti/tools', $tokenA)->getStatusCode());
    }

    private function login(string $slug): string
    {
        $response = $this->request($slug, 'POST', '/api/auth/login', null, [
            'email' => "admin@{$slug}.ulams.app",
            'password' => getenv('TENANT_DEMO_PASSWORD') ?: 'secret',
        ]);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data']['token'];
    }

    private function courseTitles(string $slug): array
    {
        $response = $this->request($slug, 'GET', '/api/courses?per_page=100');
        $this->assertSame(200, $response->getStatusCode());

        return array_column(json_decode((string) $response->getBody(), true)['data'] ?? [], 'title');
    }

    private function request(string $slugOrHost, string $method, string $uri, ?string $token = null, ?array $json = null): ResponseInterface
    {
        $host = str_contains($slugOrHost, '.') ? $slugOrHost : "{$slugOrHost}.localhost";
        $options = ['headers' => array_filter([
            'Host' => $host,
            'Accept' => 'application/json',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ])];
        if ($json !== null) {
            $options['json'] = $json;
        }

        return self::$http->request($method, $uri, $options);
    }

    /**
     * Runs a platform artisan command. The test process carries test-database variables
     * (phpunit.xml, docker -e), which the child must not inherit.
     */
    private static function artisan(array $arguments): void
    {
        $base = dirname(__DIR__, 4);
        $env = array_fill_keys(array_unique([...array_keys(getenv()), ...array_keys($_ENV)]), false);
        foreach (['PATH', 'HOME', 'TENANT_DEMO_PASSWORD'] as $keep) {
            if (getenv($keep) !== false) {
                $env[$keep] = getenv($keep);
            }
        }

        $process = new Process([PHP_BINARY, $base . '/artisan', ...$arguments, '--no-interaction'], $base, $env, null, 900);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('artisan ' . implode(' ', $arguments) . " failed:\n" . $process->getOutput() . $process->getErrorOutput());
        }
    }
}
