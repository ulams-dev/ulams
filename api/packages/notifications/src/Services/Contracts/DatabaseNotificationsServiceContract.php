<?php

namespace EscolaLms\Notifications\Services\Contracts;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Models\User;
use EscolaLms\Notifications\Dtos\NotificationsFilterCriteriaDto;
use EscolaLms\Notifications\Dtos\PageDto;
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
