<?php

namespace Ulams\Auth\Repositories\Criteria;

use Carbon\Carbon;
use Ulams\Auth\Events\Login;
use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class LastLoginCriterion extends Criterion
{
    public function __construct(int $days, ?string $operator = '>')
    {
        parent::__construct('created_at', Carbon::now()->subDays($days), $operator);
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->whereRaw("(SELECT MAX(notifications.{$this->key}) FROM notifications WHERE notifiable_id = users.id AND event = '".Login::class."' GROUP BY notifiable_id) {$this->operator} '{$this->value}'");
    }
}
