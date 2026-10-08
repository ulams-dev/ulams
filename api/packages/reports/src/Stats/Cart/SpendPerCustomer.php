<?php

namespace EscolaLms\Reports\Stats\Cart;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\Order;

class SpendPerCustomer extends AbstractCartStat
{
    public function calculate(): int
    {
        $orderTable = (new Order())->getTable();

        return Order::query()
            ->selectRaw('SUM(' . $orderTable . '.total) / COUNT(' . $orderTable . '.user_id) as value')
            ->where('status', '=', OrderStatus::PAID)
            ->first()
            ->value ?? 0;
    }
}
