<?php

namespace Ulams\Compliance\Console;

use Illuminate\Console\Command;
use Ulams\Compliance\Services\DeadlineProcessor;

class ProcessComplianceCommand extends Command
{
    protected $signature = 'compliance:process';

    protected $description = 'Send certificate and training reminders, start recertifications and escalate overdue training to managers';

    public function handle(DeadlineProcessor $processor): int
    {
        foreach ($processor->process() as $kind => $count) {
            $this->line(sprintf('%-24s %d', $kind, $count));
        }

        return self::SUCCESS;
    }
}
