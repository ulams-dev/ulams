<?php

namespace Ulams\Webhooks\Console;

use Illuminate\Console\Command;
use Ulams\Webhooks\Jobs\DeliverWebhookJob;
use Ulams\Webhooks\Services\DeliveryRunner;

class RetryDueCommand extends Command
{
    protected $signature = 'webhooks:retry-due {--grace= : Seconds a retry may be overdue before it is queued again}';

    protected $description = 'Queue webhook retries that are overdue (a lost job, or the synchronous queue)';

    public function handle(DeliveryRunner $runner): int
    {
        $grace = $this->option('grace') !== null ? (int) $this->option('grace') : (DeliverWebhookJob::delaysSupported() ? 300 : 0);
        $this->info('Queued ' . $runner->retryDue($grace) . ' overdue retries.');

        return self::SUCCESS;
    }
}
