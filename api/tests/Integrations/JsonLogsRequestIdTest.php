<?php

namespace Tests\Integrations;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class JsonLogsRequestIdProbeJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(): void
    {
        Log::info('probe job ran');
    }
}

/**
 * The stderr_json channel writes one JSON object per line, and the request id of the request that
 * dispatched a job is in the worker's lines (P8-4, docs/operators/monitoring).
 */
class JsonLogsRequestIdTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'ulams-log');
        $channel = config('logging.channels.stderr_json');
        $this->assertNotNull($channel, 'logging.channels.stderr_json is missing');
        $channel['with']['stream'] = $this->file;
        config([
            'logging.channels.json_probe' => $channel,
            'logging.default' => 'json_probe',
            'ulams_tenancy.enforce_known_hosts' => false,
            'queue.default' => 'database',
        ]);
        Log::forgetChannel('json_probe');
        DB::table('jobs')->delete();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        DB::table('jobs')->delete();
        parent::tearDown();
    }

    /** @return list<array<string, mixed>> */
    private function lines(): array
    {
        $lines = array_filter(explode("\n", (string) file_get_contents($this->file)));
        return array_values(array_map(function (string $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded, "Not a JSON object: {$line}");

            return $decoded;
        }, $lines));
    }

    public function testALineIsOneJsonObjectWithTheContextFields(): void
    {
        Route::get('/__probe/log', function () {
            Log::warning('probe line', ['order' => 7]);

            return 'ok';
        });
        $id = (string) Str::ulid();

        $this->get('/__probe/log', ['X-Request-Id' => $id, 'Host' => 'api.localhost'])->assertOk();

        $line = collect($this->lines())->firstWhere('message', 'probe line');
        $this->assertNotNull($line);
        $this->assertSame('WARNING', $line['level_name']);
        $this->assertSame(7, $line['context']['order']);
        $this->assertSame($id, $line['extra']['request_id']);
        $this->assertSame('platform', $line['extra']['tenant']);
        $this->assertSame('__probe/log', $line['extra']['route']);
        $this->assertArrayHasKey('host', $line['extra']);
        $this->assertArrayNotHasKey('email', $line['extra']);
    }

    public function testTheRequestIdReachesTheQueuedJob(): void
    {
        Route::get('/__probe/dispatch', function () {
            JsonLogsRequestIdProbeJob::dispatch();

            return 'queued';
        });
        $id = (string) Str::ulid();

        $this->get('/__probe/dispatch', ['X-Request-Id' => $id])->assertOk();
        $this->assertSame(1, DB::table('jobs')->count());

        // the worker is another process: nothing of the request is left in memory
        Context::flush();
        Log::forgetChannel('json_probe');
        Artisan::call('queue:work', ['--once' => true, '--queue' => 'default', '--stop-when-empty' => true]);

        $line = collect($this->lines())->firstWhere('message', 'probe job ran');
        $this->assertNotNull($line, 'the job did not log');
        $this->assertSame($id, $line['extra']['request_id']);
    }
}
