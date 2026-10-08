<?php

namespace Ulams\Notifications\Services;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Models\User;
use Ulams\Core\Repositories\Criteria\Criterion;
use Ulams\Notifications\Dtos\NotificationsFilterCriteriaDto;
use Ulams\Notifications\Dtos\PageDto;
use Ulams\Notifications\Models\DatabaseNotification;
use Ulams\Notifications\Models\User as NotificationsUser;
use Ulams\Notifications\Services\Contracts\DatabaseNotificationsServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DatabaseNotificationsService implements DatabaseNotificationsServiceContract
{
    public function getUserNotifications(
        User $user,
        NotificationsFilterCriteriaDto $notificationsFilterDto,
        PageDto $pageDto,
        OrderDto $orderDto
    ): LengthAwarePaginator
    {
        $user = $user instanceof NotificationsUser ? $user : NotificationsUser::find($user->getKey());

        $query = $user->notifications()
            ->whereNotIn('event', config('ulams_notifications.except_events'))->getQuery();
        $query = $this->applyCriteria($query, $notificationsFilterDto->toArray());
        if ($orderDto->getOrderBy()) {
            $query
                ->reorder()
                ->orderBy($orderDto->getOrderBy(), $orderDto->getOrder() ?? 'asc');
        }

        return $query->paginate($pageDto->getPerPage());
    }

    public function getAllNotifications(
        NotificationsFilterCriteriaDto $notificationsFilterDto,
        PageDto $pageDto,
        OrderDto $orderDto
    ): LengthAwarePaginator
    {
        $query = DatabaseNotification::query();
        $query = $this->applyCriteria($query, $notificationsFilterDto->toArray());
        if ($orderDto->getOrderBy()) {
            $query->orderBy($orderDto->getOrderBy(), $orderDto->getOrder() ?? 'asc');
        }
        return $query->paginate($pageDto->getPerPage());
    }

    public function getEvents(): array
    {
        return DatabaseNotification::select('event')
            ->distinct()
            ->pluck('event')
            ->toArray();
    }

    public function markAsReadAll(User $user): void
    {
        $user->unreadNotifications->each(fn ($notification) => $notification->markAsRead());
    }

    private function applyCriteria(Builder $query, array $criteria): Builder
    {
        foreach ($criteria as $criterion) {
            if ($criterion instanceof Criterion) {
                $query = $criterion->apply($query);
            }
        }

        return $query;
    }
}
