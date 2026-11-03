<?php

namespace Tests\Integrations;

use App\Health\HeartbeatCheck;
use App\Health\ObjectStorageCheck;
use App\Health\ServiceReachableCheck;
use App\Providers\HealthCheckProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Ulams\Core\Observability\Heartbeat;
use Ulams\Core\Observability\QueueHeartbeatJob;

/** P8-4: /up is liveness (no dependency), /api/health is readiness with the new checks. */
class LivenessAndReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function testUpAnswersWithoutTouchingTheDatabaseOnAnyHost(): void
    {
        config(['ulams_tenancy.enforce_known_hosts' => true]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->get('/up', ['Host' => '10.42.0.17'])->assertOk()->assertJson(['status' => 'up']);

        $this->assertSame([], $queries);
    }

    public function testTheHeartbeatCommandRecordsTheSchedulerAndSendsAJobThroughTheQueue(): void
    {
        config(['queue.default' => 'sync']);
        $this->assertNull(Heartbeat::age(Heartbeat::SCHEDULER));
        $this->assertNull(Heartbeat::age(Heartbeat::QUEUE));

        $this->artisan('ulams:heartbeat')->assertSuccessful();

        $this->assertSame(0, Heartbeat::age(Heartbeat::SCHEDULER));
        $this->assertSame(0, Heartbeat::age(Heartbeat::QUEUE));
    }

    public function testTheQueueHeartbeatOnlyBeatsWhenAWorkerRunsTheJob(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->artisan('ulams:heartbeat')->assertSuccessful();

        \Illuminate\Support\Facades\Queue::assertPushed(QueueHeartbeatJob::class);
        $this->assertNull(Heartbeat::age(Heartbeat::QUEUE));
    }

    public function testAStaleHeartbeatIsOnlyAFailureWhenEnforced(): void
    {
        Cache::put('ulams:heartbeat:queue', time() - 600);
        $check = HeartbeatCheck::for(Heartbeat::QUEUE);

        config(['health.require_heartbeats' => false]);
        $this->assertSame('ok', $check->run()->status->value);

        config(['health.require_heartbeats' => true]);
        $this->assertSame('failed', $check->run()->status->value);

        Heartbeat::beat(Heartbeat::QUEUE);
        $this->assertSame('ok', $check->run()->status->value);
    }

    public function testAMissingPdfServiceIsAWarningTextNotAFailure(): void
    {
        Http::fake(['pdf.test/*' => Http::response('', 500)]);

        $result = ServiceReachableCheck::for('pdf', 'http://pdf.test')->run();

        $this->assertSame('ok', $result->status->value);
        $this->assertStringContainsString('Warning', $result->notificationMessage);
        $this->assertNull(ServiceReachableCheck::for('pdf', null));
    }

    public function testObjectStorageCheckPassesWithoutABucket(): void
    {
        config(['filesystems.disks.s3.bucket' => '']);

        $this->assertSame('ok', ObjectStorageCheck::new()->run()->status->value);
    }

    public function testReadinessListsTheNewChecks(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'database', 'health.check_horizon' => false]);

        $names = array_map(fn ($check) => $check->getName(), HealthCheckProvider::checks());

        $this->assertContains('scheduler-heartbeat', $names);
        $this->assertContains('queue-heartbeat', $names);
        $this->assertContains('object-storage', $names);
        $this->assertNotContains('Horizon', $names);
    }
}
