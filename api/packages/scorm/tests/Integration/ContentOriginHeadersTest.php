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
        $this->assertStringContainsString("frame-ancestors 'self' http://coffee.app.localhost http://coffee.admin.localhost", $csp);
        $this->assertStringContainsString("form-action 'none'", $csp);
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        $this->assertSame('', $response->getHeaderLine('Set-Cookie'));
    }

    public function testOnlyReadsOfPackagePathsAreServed(): void
    {
        $this->assertSame(404, $this->get('coffee.content.localhost', '/api/profile/me')->getStatusCode());
        $this->assertSame(404, $this->get('coffee.content.localhost', '/avatars/x.png')->getStatusCode());
        $this->assertSame(404, $this->http->request('PUT', '/scorm/x', ['headers' => ['Host' => 'coffee.content.localhost']])->getStatusCode());
    }

    public function testEachTenantOriginMapsToItsOwnBucketOnly(): void
    {
        $this->assertStringContainsString('<BucketName>ulams-coffee</BucketName>', (string) $this->get('coffee.content.localhost', '/scorm/missing')->getBody());
        $this->assertStringContainsString('<BucketName>ulams-tea</BucketName>', (string) $this->get('tea.content.localhost', '/scorm/missing')->getBody());

        foreach (['/scorm/../../ulams-tea/scorm/x', '/scorm/%2e%2e/%2e%2e/ulams-tea/scorm/x', '/scorm/a/%2e%2e%2f%2e%2e%2f%2e%2e%2fulams-tea/x'] as $path) {
            $body = (string) $this->get('coffee.content.localhost', $path)->getBody();
            $this->assertStringNotContainsString('ulams-tea', $body, $path);
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
