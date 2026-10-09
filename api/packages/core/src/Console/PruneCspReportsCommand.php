<?php

namespace Ulams\Core\Console;

use Illuminate\Console\Command;
use Ulams\Core\Services\Contracts\CspReportServiceContract;

class PruneCspReportsCommand extends Command
{
    protected $signature = 'csp-reports:prune {--days= : Keep reports seen within this many days (default: ulams.core.csp.retention_days)}';

    protected $description = 'Delete aggregated CSP violation reports that were not seen for a while';

    public function handle(CspReportServiceContract $reports): int
    {
        $days = (int) ($this->option('days') ?: config('ulams.core.csp.retention_days', 30));
        $this->info('Deleted ' . $reports->prune($days) . ' CSP report row(s) older than ' . $days . ' days.');

        return self::SUCCESS;
    }
}
