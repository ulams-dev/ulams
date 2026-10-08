<?php

namespace EscolaLms\Reports\Stats\User;

use EscolaLms\Core\Models\User;
use EscolaLms\Notifications\Models\DatabaseNotification;
use Illuminate\Support\Collection;

class ActiveUsers extends AbstractUsersStats
{
    public function calculate(): Collection
    {
        return DatabaseNotification::query()
            ->selectRaw('DATE(created_at) AS date, COUNT(DISTINCT notifiable_id) AS count')
            ->whereDate('created_at', '>=', $this->dateRange->getDateFrom())
            ->whereDate('created_at', '<=', $this->dateRange->getDateTo())
            ->groupBy('date')
            ->get(['date', 'count'])
            ->mapWithKeys(fn(DatabaseNotification $model) => [$model->date => $model->count]);
    }

    public static function requiredPackagesInstalled(): bool
    {
        return class_exists(User::class) && class_exists(DatabaseNotification::class);
    }
}
