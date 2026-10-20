<?php

namespace Ulams\LearnerInsights\Console;

use Illuminate\Console\Command;
use Ulams\LearnerInsights\Remediation\RemediationService;

/** Retires remediations whose element was changed (a new applied version): they are regenerated on the next struggle. */
class RetireRemediationsCommand extends Command
{
    protected $signature = 'learner-insights:retire-remediations';

    protected $description = 'Retire cached remediations of element versions that are no longer applied';

    public function handle(RemediationService $service): int
    {
        $this->info('Retired ' . $service->retireOutdated() . ' remediations.');

        return self::SUCCESS;
    }
}
