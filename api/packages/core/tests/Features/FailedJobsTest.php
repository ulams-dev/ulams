<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Core\Tests\TestCase;

/** failed_jobs.uuid is NOT NULL: the failer must be the uuid-aware one, or no failure can be recorded. */
class FailedJobsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_app_queue_config_uses_the_uuid_failed_job_driver(): void
    {
        $config = require dirname(__DIR__, 4) . '/config/queue.php';

        $this->assertSame('database-uuids', $config['failed']['driver']);
        $this->assertSame('failed_jobs', $config['failed']['table']);
    }

    public function test_a_failed_job_is_recorded_with_its_uuid(): void
    {
        $config = require dirname(__DIR__, 4) . '/config/queue.php';
        config()->set('queue.failed', array_merge($config['failed'], ['database' => config('database.default')]));
        $this->app->forgetInstance('queue.failer');

        $uuid = (string) Str::uuid();
        $id = app('queue.failer')->log('redis', 'default', json_encode(['uuid' => $uuid, 'job' => 'x']), new \RuntimeException('boom'));

        $this->assertNotNull($id);
        $this->assertSame($uuid, DB::table('failed_jobs')->where('uuid', $uuid)->value('uuid'));
    }
}
