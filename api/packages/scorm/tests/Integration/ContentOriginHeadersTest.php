<?php

namespace Ulams\Scorm\Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Checks the Caddy content origin (docker/conf/Caddyfile) over HTTP. Opt-in, needs Caddy and
 * MinIO from the compose stack:
 *
 *   docker compose exec -T -e CONTENT_ORIGIN_INTEGRATION_URL=http://caddy api \
 *     vendor/bin/phpunit packages/scorm/tests/Integration
 */
class ContentOriginHeadersTest extends TestCase
{
    private Client $http;

    protected function setUp(): void
    {
        $url = getenv('CONTENT_ORIGIN_INTEGRATION_URL');
        if (!$url) {
            $this->markTestSkipped('Set CONTENT_ORIGIN_INTEGRATION_URL (e.g. http://caddy) to run against Caddy.');
        }
        $this->http = new Client(['base_uri' => $url, 'http_errors' => false, 'timeout' => 10]);
    }

    public function testPackagePathsCarryTheContentSecurityPolicy(): void
    {
        $response = $this->get('coffee.content.localhost', '/scorm/_player/player.html');

        $csp = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('connect-src \'self\' http://coffee.localhost', $csp);
        $this->assertStringContainsString("frame-ancestors 'self' http://coffee.app.localhost http://coffee.app.localhost:4321 http://coffee.admin.localhost", $csp);
        $this->assertStringContainsString("form-action 'none'", $csp);
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        $this->assertSame('', $response->getHeaderLine('Set-Cookie'));
    }

    public function testOnlyReadsOfPackagePathsAreServed(): void
    {
        $this->assertSame(404, $this->get('coffee.content.localhost', '/api/profile/me')->getStatusCode());
        $this->assertSame(404, $this->get('coffee.content.localhost', '/avatars/x.png')->getStatusCode());
        $this->assertSame(404, $this->get('coffee.content.localhost', '/api/content/scorm/x')->getStatusCode());
        $this->assertSame(404, $this->http->request('PUT', '/scorm/x', ['headers' => ['Host' => 'coffee.content.localhost']])->getStatusCode());
    }

    public function testPackageFilesComeFromTheTenantApiWithoutTraversal(): void
    {
        // proxied to the tenant API (GET /api/content/...): unknown files are a 404 from Laravel
        $missing = $this->get('coffee.content.localhost', '/scorm/missing.html');
        $this->assertSame(404, $missing->getStatusCode());
        $this->assertSame('', $missing->getHeaderLine('Set-Cookie'));

        foreach (['/scorm/../../avatars/x.png', '/scorm/%2e%2e/%2e%2e/avatars/x.png', '/scorm/a/%2e%2e%2f%2e%2e%2favatars/x.png'] as $path) {
            $this->assertSame(404, $this->get('coffee.content.localhost', $path)->getStatusCode(), $path);
        }
    }

    public function testSvgOnTheStorageOriginRunsNoScript(): void
    {
        $response = $this->get('storage.localhost', '/ulams/any.svg');

        $this->assertStringContainsString("script-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    private function get(string $host, string $path): ResponseInterface
    {
        // raw path: Guzzle must not normalise the dot segments the test is about
        return $this->http->request('GET', $path, ['headers' => ['Host' => $host]]);
    }
}
