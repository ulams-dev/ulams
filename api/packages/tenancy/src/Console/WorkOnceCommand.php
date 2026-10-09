<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;

/**
 * One pass of background work for one domain (`--domain=<host>`, or the platform), for hosts that
 * cannot keep a long-lived process (shared hosting, cron-only environments): run the scheduler
 * tick of this minute, then drain a queue and exit. A crontab line per domain and queue group
 * replaces what workers.sh supervises on a server with Docker (ADR 0091).
 *
 * It is `queue:work --stop-when-empty --max-time` plus an optional `schedule:run`, in one process,
 * so the domain's Laravel boots once per minute for both. Overlapping runs of the same line must be
 * prevented by the caller (flock), because a scheduler tick taken twice runs its tasks twice.
 */
class WorkOnceCommand extends Command
{
    protected $signature = 'ulams:tenant:work-once
        {--queue=default,broadcast,video : Comma-separated queues to drain, highest priority first}
        {--connection= : Queue connection (default: the default connection; builder and long jobs use <driver>-builder and <driver>-long-job)}
        {--timeout=60 : Seconds a single job may run (builder jobs run up to 1800, long jobs up to 18000)}
        {--max-time=50 : Stop taking new jobs after this many seconds}
        {--memory=256 : Memory limit of the worker in MB}
        {--schedule : Run one scheduler tick before the queue (only on the line that runs every minute)}';

    protected $description = 'Run the scheduler tick and drain a queue once, then exit (for cron instead of long-lived workers)';

    public function handle(): int
    {
        if ($this->option('schedule')) {
            $status = $this->call('ulams:tenant:schedule-loop', ['--once' => true]);
            if ($status !== self::SUCCESS) {
                // the queue is still worth draining, but cron should see the failure
                $this->error('The scheduler tick failed.');
            }
        }

        $arguments = [
            '--queue' => (string) $this->option('queue'),
            '--stop-when-empty' => true,
            '--sleep' => 1,
            '--timeout' => (int) $this->option('timeout'),
            '--max-time' => max(1, (int) $this->option('max-time')),
            '--memory' => (int) $this->option('memory'),
        ];
        if ($this->option('connection')) {
            $arguments = ['connection' => (string) $this->option('connection')] + $arguments;
        }

        $worker = $this->call('queue:work', $arguments);

        return ($status ?? self::SUCCESS) !== self::SUCCESS ? self::FAILURE : $worker;
    }
}
