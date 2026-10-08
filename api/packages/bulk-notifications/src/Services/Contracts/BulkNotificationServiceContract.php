<?php

namespace Ulams\BulkNotifications\Services\Contracts;

use Ulams\BulkNotifications\Dtos\OrderDto;
use Ulams\BulkNotifications\Dtos\PageDto;
use Ulams\BulkNotifications\Dtos\SendUserBulkNotificationDto;
use Ulams\BulkNotifications\Dtos\SendMulticastBulkNotificationDto;
use Ulams\BulkNotifications\Models\BulkNotification;
use Ulams\BulkNotifications\Dtos\CriteriaBulkNotificationDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BulkNotificationServiceContract
{
    public function send(SendUserBulkNotificationDto $dto): BulkNotification;

    public function sendMulticast(SendMulticastBulkNotificationDto $dto): BulkNotification;

    public function list(CriteriaBulkNotificationDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator;
}
