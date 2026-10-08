<?php

namespace Ulams\Demo\Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ulams\Tenancy\Services\ProcessTenantCommandRunner;

/**
 * Resets a running demo tenant twice and checks that its H5P content does not pile up: the
 * H5P service holds as many contents after the second reset as after the first. Wipes that
 * tenant, so it is opt-in twice over:
 *
 *   docker compose exec -T -e DEMO_INTEGRATION=1 -e DEMO_INTEGRATION_RESET=1 api \
 *     vendor/bin/phpunit packages/demo/tests/Integration/DemoResetH5PTest.php
 *
 * DEMO_INTEGRATION_RESET_HOST (default coffee.localhost) is the tenant; DEMO_INTEGRATION_URL
 * (default http://caddy) is where requests go.
 */
class DemoResetH5PTest extends TestCase
{
    private Client $http;
    private string $host;

    protected function setUp(): void
    {
        if (!getenv('DEMO_INTEGRATION') || !getenv('DEMO_INTEGRATION_RESET')) {
            $this->markTestSkipped('Set DEMO_INTEGRATION=1 and DEMO_INTEGRATION_RESET=1 to reset a running demo tenant (see class docblock).');
        }

        $this->host = getenv('DEMO_INTEGRATION_RESET_HOST') ?: 'coffee.localhost';
        $this->http = new Client([
            'base_uri' => getenv('DEMO_INTEGRATION_URL') ?: 'http://caddy',
            'http_errors' => false,
            'timeout' => 60,
        ]);
    }

    public function testH5PContentCountStaysTheSameAcrossResets(): void
    {
        $this->reset();
        $afterFirst = $this->h5pContentCount();

        $this->reset();
        $afterSecond = $this->h5pContentCount();

        $this->assertSame($afterFirst, $afterSecond, 'H5P contents after the first and the second reset');
    }

    private function reset(): void
    {
        // The tenancy runner scrubs the variables phpunit.xml exported (test database etc.), so
        // the child boots with the tenant's .env file like the scheduler does.
        $runner = new ProcessTenantCommandRunner(dirname(__DIR__, 4), PHP_BINARY, 1800);
        try {
            $runner->run($this->host, ['ulams:demo:reset', '--force']);
        } catch (RuntimeException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    /** Total of `GET /h5p/contents` as the demo admin (tokens do not survive a reset). */
    private function h5pContentCount(): int
    {
        $login = $this->http->post('/api/demo/login', [
            'headers' => ['Host' => $this->host, 'Accept' => 'application/json'],
            'json' => ['role' => 'admin'],
        ]);
        $this->assertSame(200, $login->getStatusCode(), (string) $login->getBody());
        $token = json_decode((string) $login->getBody(), true)['data']['token'];

        $list = $this->http->get('/h5p/contents?perPage=1', [
            'headers' => ['Host' => $this->host, 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token],
        ]);
        $this->assertSame(200, $list->getStatusCode(), (string) $list->getBody());

        return (int) json_decode((string) $list->getBody(), true)['meta']['total'];
    }
}
