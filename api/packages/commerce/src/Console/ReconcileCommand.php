<?php

namespace Ulams\Commerce\Console;

use Illuminate\Console\Command;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Contracts\ReconcilesOrders;
use Ulams\Commerce\Jobs\ApplyOrderEvent;
use Ulams\Commerce\Models\OrderEventRecord;
use Ulams\Commerce\Models\SyncState;
use Ulams\Commerce\Services\OrderEventIntake;

/**
 * Repairs missed webhooks (ADR 0130, section 5.5). Reads the provider's event feed from the stored cursor,
 * stores every event the LMS has not seen and queues it through the same job the webhook uses, then queues
 * events that were stored but never applied. The feed does not depend on the delivery path, so a blocked
 * webhook URL or a dead worker on the provider side is repaired too.
 *
 * Skeleton for CM-1: the nightly `--full` sweep (compare paid orders with entitlements, both directions) follows with
 * the Sylius adapter.
 */
class ReconcileCommand extends Command
{
    protected $signature = 'ulams:commerce:reconcile
        {--provider= : Provider key, default commerce.provider}
        {--retry-failed : Queue events whose job gave up (outcome "failed") again}';

    protected $description = 'Apply order events the commerce provider recorded but never delivered';

    public function handle(CommerceManager $commerce, OrderEventIntake $intake): int
    {
        $key = (string) ($this->option('provider') ?: config('commerce.provider'));
        if (!$commerce->has($key)) {
            $this->error("Unknown commerce provider \"{$key}\".");

            return self::FAILURE;
        }
        $provider = $commerce->provider($key);
        if (!$provider instanceof ReconcilesOrders) {
            $this->line("Provider \"{$key}\" has no event feed; nothing to reconcile.");

            return self::SUCCESS;
        }
        $channel = (string) config('commerce.channel');
        if ($channel === '') {
            $this->error('commerce.channel (COMMERCE_CHANNEL) is not set.');

            return self::FAILURE;
        }

        $state = SyncState::query()->firstOrCreate(['provider' => $key, 'channel' => $channel]);
        $limit = max(1, (int) config('commerce.reconcile.page_size'));
        $queued = 0;
        $cursor = $state->cursor;
        do {
            $page = $provider->orderEventsAfter($cursor, $limit);
            foreach ($page->events as $event) {
                if ($event->channel !== $channel) {
                    $this->warn("Skipped event {$event->eventId}: another channel.");
                    continue;
                }
                $record = $intake->record($key, $event, OrderEventIntake::SOURCE_RECONCILE, hash('sha256', json_encode($event)));
                if ($record !== null) {
                    ApplyOrderEvent::dispatch($record->id);
                    $queued++;
                }
            }
            $advanced = $page->nextCursor !== null && $page->nextCursor !== $cursor;
            if ($advanced) {
                $cursor = $page->nextCursor;
                $state->forceFill(['cursor' => $cursor])->save(); // progress survives a failure on a later page
            }
        } while ($page->events !== [] && $advanced);

        if ($this->option('retry-failed')) {
            OrderEventRecord::query()->where('provider', $key)->where('outcome', OrderEventRecord::OUTCOME_FAILED)
                ->update(['outcome' => null, 'error' => null, 'attempts' => 0]);
        }
        $stuck = OrderEventRecord::query()->where('provider', $key)->whereNull('outcome')
            ->where('received_at', '<', now()->subMinutes((int) config('commerce.reconcile.stuck_after_minutes')))
            ->pluck('id');
        foreach ($stuck as $id) {
            ApplyOrderEvent::dispatch($id);
        }

        $state->forceFill(['last_run_at' => now(), 'last_applied' => $queued])->save();
        $this->info("Reconciled \"{$key}\": {$queued} missed event(s) queued, {$stuck->count()} stuck event(s) queued again.");

        return self::SUCCESS;
    }
}
