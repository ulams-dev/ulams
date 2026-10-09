<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * A long-lived scheduler for one domain (`--domain=<host>`, or the platform): runs the
 * scheduler in-process at the start of every minute, so the domain's Laravel boots once per
 * --max-time instead of once a minute. workers.sh starts one per domain and restarts it when it
 * exits.
 *
 * With several API replicas every replica runs this loop, so each minute is claimed with a lock
 * in the shared cache (ADR 0021, ADR 0035): the replica that gets it runs the tick, the others
 * skip that minute. `--once` is a manual tick and does not take the lock.
 */
class ScheduleLoopCommand extends Command
{
    protected $signature = 'ulams:tenant:schedule-loop
        {--max-time=3600 : Exit after this many seconds (the supervisor starts it again with fresh code and config)}
        {--once : Run one scheduler tick now and exit}';

    protected $description = 'Run the scheduler of this domain every minute in one long-lived process';

    private bool $stopping = false;

    public function handle(): int
    {
        $this->trap([SIGTERM, SIGINT, SIGQUIT], fn () => $this->stopping = true);

        if ($this->option('once')) {
            return $this->tick();
        }

        $until = time() + max(60, (int) $this->option('max-time'));
        while (!$this->stopping && time() < $until) {
            $this->sleepUntilNextMinute();
            if ($this->stopping) {
                break;
            }
            $this->runMinute(Carbon::now());
        }

        return self::SUCCESS;
    }

    /**
     * Runs the scheduler tick of this minute unless another replica already claimed it.
     * Returns whether this process ran it.
     */
    public function runMinute(Carbon $minute): bool
    {
        if (!$this->claim($minute)) {
            return false;
        }
        $this->tick();

        return true;
    }

    /**
     * An atomic lock in the cache store (Valkey in the stack, prefixed per tenant). The lock is
     * not released: it expires, so a replica that is a few seconds behind does not run the same
     * minute again. A store without locks, or TENANCY_SCHEDULER_LOCK=false, means a single
     * scheduler: every tick runs.
     */
    protected function claim(Carbon $minute): bool
    {
        if (!config('ulams_tenancy.scheduler_lock', true)) {
            return true;
        }
        $store = Cache::store()->getStore();
        if (!$store instanceof LockProvider) {
            return true;
        }
        $name = 'ulams:schedule-tick:' . (config('ulams_tenancy.tenant_slug') ?: 'platform') . ':' . $minute->format('YmdHi');

        return $store->lock($name, 90)->get();
    }

    /**
     * A new ScheduleRunCommand per tick: it records its start time in its constructor, and
     * that time decides which events are due.
     */
    protected function tick(): int
    {
        $command = new ScheduleRunCommand();
        $command->setLaravel($this->laravel);
        $command->setApplication($this->getApplication());

        return $command->run(new ArrayInput([]), $this->output ?? new NullOutput());
    }

    protected function sleepUntilNextMinute(): void
    {
        $seconds = 60 - (time() % 60);
        // sleep() returns early when a trapped signal arrives
        while ($seconds > 0 && !$this->stopping) {
            $seconds = sleep($seconds);
        }
    }
}
