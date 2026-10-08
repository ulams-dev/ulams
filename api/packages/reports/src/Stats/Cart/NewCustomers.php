<?php

namespace Ulams\Reports\Stats\Cart;

use Ulams\Cart\Models\Order;
use Ulams\Core\Models\User;
use Illuminate\Support\Carbon;

class NewCustomers extends AbstractCartStat
{
    public function calculate(): int
    {
        $userTable = (new User())->getTable();

        return Order::query()
            ->select('user_id')
            ->distinct()
            ->whereIn('user_id', fn($query) => $query
                ->from($userTable)
                ->select('id')
                ->whereDate('created_at', Carbon::today())
            )
            ->count();
    }
}
