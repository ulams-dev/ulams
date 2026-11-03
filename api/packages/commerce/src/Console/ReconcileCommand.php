<?php

namespace Ulams\Commerce\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Contracts\ListsOrders;
use Ulams\Commerce\Models\DriftReport;
use Ulams\Commerce\Services\OrderEventApplier;
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
 * `--full` is the nightly sweep: it lists the channel's orders of the last 35 days through the provider's `ListsOrders`
 * capability (no feed, no webhooks involved) and repairs both directions, a paid line without an entitlement and a
 * refunded or cancelled line that still grants access. Every run writes a drift report; `--dry-run` only reports.
 */
class ReconcileCommand extends Command
{
    protected $signature = 'ulams:commerce:reconcile
        {--provider= : Provider key, default commerce.provider}
        {--retry-failed : Queue events whose job gave up (outcome "failed") again}
        {--full : The nightly sweep: compare the channel\'s recent orders with entitlements and repair both directions}
        {--since= : With --full, look back to this date (YYYY-MM-DD) instead of 35 days}
        {--dry-run : With --full, report what would change without changing it}';

    protected $description = 'Apply order events the commerce provider recorded but never delivered';

    public function handle(CommerceManager $commerce, OrderEventIntake $intake, OrderEventApplier $applier): int
    {
        $key = (string) ($this->option('provider') ?: config('commerce.provider'));
        if (!$commerce->has($key)) {
            $this->error("Unknown commerce provider \"{$key}\".");

            return self::FAILURE;
        }
        $provider = $commerce->provider($key);
        if ($this->option('full')) {
            return $this->sweep($provider, $key, $applier);
        }
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

    private function sweep(CommerceProvider $provider, string $key, OrderEventApplier $applier): int
    {
        if (!$provider instanceof ListsOrders) {
            $this->line("Provider \"{$key}\" cannot list orders; nothing to sweep.");

            return self::SUCCESS;
        }
        if ((string) config('commerce.channel') === '') {
            $this->error('commerce.channel (COMMERCE_CHANNEL) is not set.');

            return self::FAILURE;
        }
        try {
            $since = $this->option('since') ? Carbon::createFromFormat('Y-m-d', (string) $this->option('since'))->startOfDay() : now()->subDays((int) config('commerce.reconcile.sweep_days'));
        } catch (\Throwable) {
            $this->error('--since must be a date like 2026-09-01.');

            return self::INVALID;
        }
        $repair = !$this->option('dry-run');
        $orders = 0;
        $changes = [];
        $problems = [];
        foreach ($provider->ordersChangedSince($since) as $snapshot) {
            $orders++;
            try {
                foreach ($applier->sweepOrder($key, $snapshot, $repair) as $change) {
                    str_starts_with($change, 'problem: ') ? $problems[] = "{$snapshot->orderId}: " . substr($change, 9) : $changes[] = "{$snapshot->orderId}: {$change}";
                }
            } catch (\Throwable $e) {
                $problems[] = "{$snapshot->orderId}: {$e->getMessage()}";
            }
        }
        foreach ($changes as $change) {
            Log::warning('commerce: the sweep ' . ($repair ? 'repaired' : 'found') . ' ' . $change, ['provider' => $key]);
        }
        $drift = count($changes) + count($problems);
        DriftReport::query()->create([
            'kind' => DriftReport::ORDERS, 'provider' => $key, 'drift' => $drift, 'repaired' => $repair ? count($changes) : 0,
            'details' => ['since' => $since->toDateString(), 'orders' => $orders, 'changes' => array_slice($changes, 0, 100), 'problems' => array_slice($problems, 0, 100)],
        ]);
        $this->info("Swept {$orders} order(s) since {$since->toDateString()}: " . count($changes) . ($repair ? ' repaired' : ' to repair') . ', ' . count($problems) . ' problem(s).');
        foreach (array_merge($changes, $problems) as $line) {
            $this->line("  {$line}");
        }

        return $problems === [] && ($repair || $changes === []) ? self::SUCCESS : self::FAILURE;
    }
}
