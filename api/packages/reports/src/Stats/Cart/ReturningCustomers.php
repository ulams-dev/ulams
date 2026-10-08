<?php

namespace EscolaLms\Reports\Stats\Cart;

use Carbon\Carbon;
use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\Order;

class ReturningCustomers extends AbstractCartStat
{
    public function calculate(): int
    {
        $orderTable = (new Order())->getTable();
        return Order::query()
            ->select('user_id')
            ->distinct()
            ->where('status', '=', OrderStatus::PAID)
            ->whereIn('user_id', fn($query) => $query->from($orderTable)
                ->select('user_id')
                ->whereIn('user_id', fn($query) => $query
                    ->select('user_id')
                    ->where('status', '=', OrderStatus::PAID)
                    ->whereDate('created_at', '<=', Carbon::now()->subYear())
                )
                ->whereNotIn('user_id', fn($query) => $query
                    ->select('user_id')
                    ->where('status', '=', OrderStatus::PAID)
                    ->whereDate('created_at', '>', Carbon::now()->subYear())
                    ->whereDate('created_at', '<', Carbon::now()->subDay())
                )
            )
            ->whereDate('created_at', Carbon::today())
            ->count('user_id');
    }
}
