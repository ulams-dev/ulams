<?php

namespace EscolaLms\BulkNotifications\Services\Contracts;

use EscolaLms\BulkNotifications\Dtos\OrderDto;
use EscolaLms\BulkNotifications\Dtos\PageDto;
use EscolaLms\BulkNotifications\Dtos\SendUserBulkNotificationDto;
use EscolaLms\BulkNotifications\Dtos\SendMulticastBulkNotificationDto;
use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Dtos\CriteriaBulkNotificationDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BulkNotificationServiceContract
{
    public function send(SendUserBulkNotificationDto $dto): BulkNotification;

    public function sendMulticast(SendMulticastBulkNotificationDto $dto): BulkNotification;

    public function list(CriteriaBulkNotificationDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator;
}
