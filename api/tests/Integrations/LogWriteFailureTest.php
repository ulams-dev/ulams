<?php

namespace Tests\Integrations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use UnexpectedValueException;

/**
 * #495: a log file the web process cannot open (for example the day's file created by a worker or an
 * `artisan` command running as root) must not turn every request that logs into an HTTP 500 that is itself
 * never logged. The default `stack` channel ignores handler failures.
 */
class LogWriteFailureTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/ulams-log-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        // A directory where today's log file should be: opening it for appending fails for every user, root
        // included (a read-only file would not stop root, which runs the tests in the container).
        mkdir($this->dir . '/laravel-' . date('Y-m-d') . '.log');
        config([
            'logging.default' => 'stack',
            'logging.channels.daily.path' => $this->dir . '/laravel.log',
            'ulams_tenancy.enforce_known_hosts' => false,
        ]);
        Log::forgetChannel('stack');
        Log::forgetChannel('daily');
    }

    protected function tearDown(): void
    {
        @rmdir($this->dir . '/laravel-' . date('Y-m-d') . '.log');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testARequestThatLogsStillAnswersWhenTheLogFileCannotBeOpened(): void
    {
        Route::get('/__log-write-failure', function () {
            Log::debug('Checking if product is buyable', ['product' => 1]);

            return response()->json(['ok' => true]);
        });

        $this->getJson('/__log-write-failure')->assertOk()->assertJson(['ok' => true]);
    }

    public function testTheSameFailureThrowsWithoutIgnoreExceptions(): void
    {
        config(['logging.channels.stack.ignore_exceptions' => false]);
        Log::forgetChannel('stack');

        $this->expectException(UnexpectedValueException::class);
        Log::debug('this cannot be written');
    }
}
