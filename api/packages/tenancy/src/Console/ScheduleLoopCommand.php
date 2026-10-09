<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * A long-lived scheduler for one domain (`--domain=<host>`, or the platform): runs the
 * scheduler in-process at the start of every minute, so the domain's Laravel boots once per
 * --max-time instead of once a minute. workers.sh starts one per domain and restarts it when it
 * exits.
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
            $this->tick();
        }

        return self::SUCCESS;
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

        return $command->run(new ArrayInput([]), $this->output);
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
