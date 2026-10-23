<?php

namespace Tests\Integrations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Ulams\Ai\Models\AiCall;
use Ulams\Core\Observability\Heartbeat;
use Ulams\Core\Observability\Metric;
use Ulams\Core\Observability\MetricsRegistry;
use Ulams\Core\Tests\Concerns\ParsesPrometheusText;

/**
 * GET /metrics (P8-4): Prometheus text for scrapers on a private network or with the token, a 404 for
 * everybody else and on tenant hosts. Gauges are computed at scrape time.
 */
class MetricsEndpointTest extends TestCase
{
    use DatabaseTransactions;
    use ParsesPrometheusText;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ulams_tenancy.enforce_known_hosts' => false,
            'ulams_tenancy.tenant_slug' => null,
            'ulams.core.metrics.token' => null,
        ]);
        Cache::flush();
    }

    private function scrape(string $ip = '127.0.0.1', array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->get('/metrics', $headers);
    }

    public function testAPrivateScraperGetsParseableExposition(): void
    {
        Heartbeat::beat(Heartbeat::SCHEDULER);

        $response = $this->scrape('10.1.2.3');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain; version=0.0.4', $response->headers->get('Content-Type'));
        $families = $this->parsePrometheusText($response->getContent());

        $this->assertSame(1.0, $families['ulams_up']['samples'][0]['value']);
        $this->assertSame('gauge', $families['ulams_queue_size']['type']);
        $this->assertContains('default', array_column(array_column($families['ulams_queue_size']['samples'], 'labels'), 'queue'));
        $this->assertArrayHasKey('ulams_failed_jobs', $families);
        $this->assertArrayHasKey('ulams_tenants', $families);
        $this->assertArrayHasKey('ulams_db_connections', $families);
        $collectors = array_column(array_column($families['ulams_collector_up']['samples'], 'labels'), 'collector');
        $this->assertEqualsCanonicalizing(['core', 'tenancy', 'ai'], $collectors);
        $beat = array_values(array_filter($families['ulams_heartbeat_age_seconds']['samples'], fn ($s) => $s['labels']['name'] === 'scheduler'));
        $this->assertLessThan(5, $beat[0]['value']);
        foreach ($families['ulams_collector_up']['samples'] as $sample) {
            $this->assertSame(1.0, $sample['value'], 'collector ' . $sample['labels']['collector'] . ' failed');
        }
    }

    public function testAPublicAddressIsDenied(): void
    {
        $this->scrape('203.0.113.9')->assertNotFound();
    }

    public function testAForwardedRequestFromTheGatewayNeedsTheToken(): void
    {
        // Caddy and Cloudflare connect from a private address; the header shows the client was outside
        $this->scrape('172.18.0.5', ['X-Forwarded-For' => '203.0.113.9'])->assertNotFound();
        $this->scrape('172.18.0.5', ['CF-Connecting-IP' => '203.0.113.9'])->assertNotFound();
    }

    public function testTheBearerTokenOpensItFromAnyAddress(): void
    {
        config(['ulams.core.metrics.token' => 's3cret-scrape-token']);

        $this->scrape('203.0.113.9', ['Authorization' => 'Bearer s3cret-scrape-token'])->assertOk();
        $this->scrape('203.0.113.9', ['Authorization' => 'Bearer wrong'])->assertNotFound();
        $this->scrape('203.0.113.9')->assertNotFound();
        $this->scrape('172.18.0.5', ['X-Forwarded-For' => '203.0.113.9', 'Authorization' => 'Bearer s3cret-scrape-token'])->assertOk();
    }

    public function testTheCidrListIsConfigurable(): void
    {
        config(['ulams.core.metrics.allowed_cidrs' => ['198.51.100.0/24']]);

        $this->scrape('198.51.100.7')->assertOk();
        $this->scrape('10.1.2.3')->assertNotFound();
    }

    public function testATenantHostDoesNotServeIt(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'acme']);

        $this->scrape('10.1.2.3')->assertNotFound();
    }

    public function testABrokenCollectorIsReportedAndTheRestStillServed(): void
    {
        app(MetricsRegistry::class)->register('broken', function () {
            throw new \RuntimeException('boom');
        });
        app(MetricsRegistry::class)->register('extra', fn () => [Metric::gauge('ulams_extra', 'Extra.', [[3, ['a' => 'b']]])]);

        $families = $this->parsePrometheusText($this->scrape('10.1.2.3')->getContent());

        $up = [];
        foreach ($families['ulams_collector_up']['samples'] as $sample) {
            $up[$sample['labels']['collector']] = $sample['value'];
        }
        $this->assertSame(0.0, $up['broken']);
        $this->assertSame(1.0, $up['extra']);
        $this->assertSame(3.0, $families['ulams_extra']['samples'][0]['value']);
    }

    public function testAiUsageIsReportedPerModelTaskAndWindow(): void
    {
        AiCall::query()->delete();
        $row = ['task' => 'course_builder', 'driver' => 'anthropic', 'model_requested' => 'model-x', 'status' => 'ok'];
        AiCall::create($row + ['input_tokens' => 100, 'output_tokens' => 40, 'cost_micro_usd' => 2_500_000, 'created_at' => now()->subMinutes(10)]);
        AiCall::create($row + ['input_tokens' => 7, 'output_tokens' => 3, 'cost_micro_usd' => 500_000, 'created_at' => now()->subHours(5)]);

        $families = $this->parsePrometheusText($this->scrape('10.1.2.3')->getContent());

        $cost = [];
        foreach ($families['ulams_ai_cost_usd']['samples'] as $s) {
            $cost[$s['labels']['model'] . '/' . $s['labels']['task'] . '/' . $s['labels']['window']] = $s['value'];
        }
        $this->assertEqualsWithDelta(2.5, $cost['model-x/course_builder/1h'], 0.0001);
        $this->assertEqualsWithDelta(3.0, $cost['model-x/course_builder/1d'], 0.0001);
        $input = array_values(array_filter($families['ulams_ai_tokens']['samples'], fn ($s) => $s['labels']['window'] === '1d' && $s['labels']['kind'] === 'input'));
        $this->assertSame(107.0, $input[0]['value']);
    }
}
