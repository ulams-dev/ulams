<?php

namespace Ulams\Notifications\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Models\User;
use Ulams\Notifications\Dtos\NotificationsFilterCriteriaDto;
use Ulams\Notifications\Dtos\PageDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DatabaseNotificationsServiceContract
{
    public function getUserNotifications(
        User $user,
        NotificationsFilterCriteriaDto $notificationsFilterDto,
        PageDto $pageDto,
        OrderDto $orderDto
    ): LengthAwarePaginator;

    public function getAllNotifications(
        NotificationsFilterCriteriaDto $notificationsFilterDto,
        PageDto $pageDto,
        OrderDto $orderDto
    ): LengthAwarePaginator;

    public function getEvents(): array;

    public function markAsReadAll(User $user): void;
}
