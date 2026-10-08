<?php

namespace Ulams\Cart\Jobs;

use Ulams\Cart\Enums\SubscriptionStatus;
use Ulams\Cart\Models\ProductUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ExpireRecursiveProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        ProductUser::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('end_date', '<=', Carbon::now()->endOfDay()->subDay())
            ->update(['status' => SubscriptionStatus::EXPIRED]);
    }
}
