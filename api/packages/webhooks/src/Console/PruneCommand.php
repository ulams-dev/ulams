<?php

namespace Ulams\Webhooks\Console;

use Illuminate\Console\Command;
use Ulams\Webhooks\Models\WebhookDelivery;

class PruneCommand extends Command
{
    protected $signature = 'webhooks:prune';

    protected $description = 'Delete webhook deliveries (and their attempts) older than the retention period';

    public function handle(): int
    {
        $days = max(1, (int) config('ulams_webhooks.retention_days'));
        $deleted = WebhookDelivery::query()->where('created_at', '<', now()->subDays($days))
            ->whereIn('status', [WebhookDelivery::SUCCEEDED, WebhookDelivery::FAILED, WebhookDelivery::CANCELLED])->delete();
        $this->info("Deleted {$deleted} deliveries older than {$days} days.");

        return self::SUCCESS;
    }
}
