<?php

namespace Tests\Integrations;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * workers.sh runs the background work in one of two modes, ULAMS_WORKERS_MODE=per-tenant (a long-lived
 * process per tenant and queue) or lean (one looping process per kind, `ulams:tenant:work-once` per
 * domain; the default of local development and the demo profile). WORKERS_DRY_RUN=1 prints the
 * commands of one pass instead of running them. The lean commands must keep the queue semantics of
 * the per-tenant workers: the same connections, queues and timeouts (ADR 0083).
 */
class WorkersModeTest extends TestCase
{
    private string $domains;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domains = tempnam(sys_get_temp_dir(), 'domains');
        file_put_contents($this->domains, "#!/bin/bash\nprintf 'coffee.localhost\\nulam.localhost\\n'\n");
        chmod($this->domains, 0755);
    }

    protected function tearDown(): void
    {
        @unlink($this->domains);
        parent::tearDown();
    }

    /** @return string[] the lines the script printed */
    private function plan(string $kind, array $env): array
    {
        $env = $env + [
            'WORKERS_DRY_RUN' => '1',
            'WORKERS_DOMAINS_COMMAND' => $this->domains,
            'PHP_BINARY' => 'php',
            'PATH' => getenv('PATH'),
        ];
        $process = proc_open(['bash', base_path('workers.sh'), $kind], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($process), $err);

        return array_values(array_filter(explode("\n", $out)));
    }

    public function testPerTenantIsTheScriptDefault(): void
    {
        $lines = $this->plan('queue', ['MULTI_DOMAINS' => 'x']);

        $this->assertCount(6, $lines, 'default, builder and long worker for each of the 2 domains');
        $this->assertStringContainsString('queue:work --queue=default,broadcast,video', $lines[0]);
        $this->assertStringNotContainsString('work-once', implode("\n", $lines));
    }

    public function testLeanRunsEveryQueueOfEveryDomainWithWorkOnce(): void
    {
        $lines = $this->plan('queue', ['ULAMS_WORKERS_MODE' => 'lean']);

        // the platform and the 2 tenants, 3 queues each (default group, builder, long-job)
        $this->assertCount(9, $lines);
        foreach ($lines as $line) {
            $this->assertStringContainsString('artisan ulams:tenant:work-once', $line);
        }
        $this->assertCount(3, array_filter($lines, fn ($l) => ! str_contains($l, '--domain=')), 'the platform has no --domain');
        $this->assertCount(3, array_filter($lines, fn ($l) => str_contains($l, '--domain=coffee.localhost')));
        $this->assertCount(3, array_filter($lines, fn ($l) => str_contains($l, '--domain=ulam.localhost')));
    }

    public function testLeanDoesNotRunThePlatformWhenMultiDomains(): void
    {
        $lines = $this->plan('queue', ['ULAMS_WORKERS_MODE' => 'lean', 'MULTI_DOMAINS' => 'x']);

        $this->assertCount(6, $lines);
        $this->assertCount(0, array_filter($lines, fn ($l) => ! str_contains($l, '--domain=')));
    }

    #[DataProvider('driverQueues')]
    public function testLeanBuilderAndLongJobQueuesKeepTheirTimeoutsAndConnections(string $driver, string $builder, string $long): void
    {
        $lines = $this->plan('queue', ['ULAMS_WORKERS_MODE' => 'lean', 'QUEUE_CONNECTION' => $driver]);

        $builderLines = array_values(array_filter($lines, fn ($l) => str_contains($l, "work-once --connection=$builder ")));
        $longLines = array_values(array_filter($lines, fn ($l) => str_contains($l, "work-once --connection=$long ")));
        $this->assertCount(3, $builderLines);
        $this->assertCount(3, $longLines);
        $this->assertStringContainsString('--queue=builder', $builderLines[0]);
        $this->assertStringContainsString('--timeout=1800', $builderLines[0]);
        $this->assertStringContainsString('--queue=queue-long-job', $longLines[0]);
        $this->assertStringContainsString('--timeout=18000', $longLines[0]);

        // worker --timeout >= job timeout and < retry_after, as for the per-tenant workers
        $this->assertGreaterThan(1800, config("queue.connections.$builder.retry_after"));
        $this->assertGreaterThan(18000, config("queue.connections.$long.retry_after"));
        $this->assertSame('builder', config("queue.connections.$builder.queue"));
    }

    public static function driverQueues(): array
    {
        return [
            'redis' => ['redis', 'redis-builder', 'redis-long-job'],
            'database' => ['database', 'database-builder', 'database-long-job'],
        ];
    }

    public function testLeanDefaultQueuesUseTheDefaultTimeout(): void
    {
        $lines = $this->plan('queue', ['ULAMS_WORKERS_MODE' => 'lean']);

        $default = array_values(array_filter($lines, fn ($l) => str_contains($l, '--queue=default,broadcast,video')));
        $this->assertCount(3, $default);
        $this->assertStringContainsString('--timeout=60', $default[0]);
        $this->assertStringNotContainsString('--connection', $default[0]);
    }

    public function testLeanSchedulerTicksEveryDomainWithTheMinuteLock(): void
    {
        $lines = $this->plan('scheduler', ['ULAMS_WORKERS_MODE' => 'lean']);

        $this->assertCount(3, $lines);
        foreach ($lines as $line) {
            $this->assertStringContainsString('artisan ulams:tenant:schedule-loop --once --lock', $line);
        }
    }

    public function testAnUnknownModeIsRejected(): void
    {
        $process = proc_open(['bash', base_path('workers.sh'), 'queue'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), ['ULAMS_WORKERS_MODE' => 'fast', 'PATH' => getenv('PATH')]);
        stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        $this->assertSame(2, proc_close($process));
        $this->assertStringContainsString('ULAMS_WORKERS_MODE', $err);
    }

    public function testTheLocalProfilesDefaultToLeanAndTurnHorizonOff(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $this->assertStringContainsString('ULAMS_WORKERS_MODE:-lean}', $compose);

        $init = file_get_contents(base_path('init.sh'));
        $this->assertStringContainsString('ENABLE_HORIZON', $init);

        $profile = file_get_contents(base_path('php-profile.sh'));
        $this->assertStringContainsString('pm.max_children', $profile);
    }
}
