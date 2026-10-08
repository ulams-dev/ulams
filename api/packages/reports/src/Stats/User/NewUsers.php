<?php

namespace EscolaLms\Reports\Stats\User;

use EscolaLms\Core\Models\User;
use Illuminate\Support\Collection;

class NewUsers extends AbstractUsersStats
{
    public function calculate(): Collection
    {
        return User::query()
            ->selectRaw('DATE(created_at) AS date, COUNT(id) AS count')
            ->whereDate('created_at', '>=', $this->dateRange->getDateFrom())
            ->whereDate('created_at', '<=', $this->dateRange->getDateTo())
            ->groupBy('date')
            ->get(['date', 'count'])
            ->mapWithKeys(fn(User $model) => [$model->date => $model->count]);
    }
}
