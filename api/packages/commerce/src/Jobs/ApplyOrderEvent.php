<?php

namespace Ulams\Commerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;
use Ulams\Commerce\Models\OrderEventRecord;
use Ulams\Commerce\Services\OrderEventApplier;

/** Applies a stored order event after the webhook answered 202. Retried with backoff; safe to run twice. */
class ApplyOrderEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 10;

    public function __construct(public readonly int $recordId)
    {
    }

    /** @return int[] */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300, 600, 900, 1800, 3600];
    }

    public function handle(OrderEventApplier $applier): void
    {
        try {
            $applier->apply($this->recordId);
        } catch (Throwable $e) {
            OrderEventRecord::query()->whereKey($this->recordId)->update(['error' => mb_substr($e->getMessage(), 0, 500), 'attempts' => DB::raw('attempts + 1')]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        OrderEventRecord::query()->whereKey($this->recordId)->whereNull('outcome')->update([
            'outcome' => OrderEventRecord::OUTCOME_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}
