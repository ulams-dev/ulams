<?php

namespace Ulams\BulkNotifications\Http\Controllers;

use Ulams\BulkNotifications\Http\Controllers\Swagger\BulkNotificationControllerSwagger;
use Ulams\BulkNotifications\Http\Requests\ListBulkNotificationRequest;
use Ulams\BulkNotifications\Http\Requests\SendUserBulkNotificationRequest;
use Ulams\BulkNotifications\Http\Requests\SendMulticastBulkNotificationRequest;
use Ulams\BulkNotifications\Http\Resources\BulkNotificationResource;
use Ulams\BulkNotifications\Services\Contracts\BulkNotificationServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class BulkNotificationController extends UlamsBaseController implements BulkNotificationControllerSwagger
{

    public function __construct(private BulkNotificationServiceContract $bulkNotificationService)
    {
    }

    public function send(SendUserBulkNotificationRequest $request): JsonResponse
    {
        $bulkNotification = $this->bulkNotificationService->send($request->toDto());

        return $this->sendResponseForResource(
            BulkNotificationResource::make($bulkNotification),
            'Notification sent successfully.'
        );
    }

    public function sendMulticast(SendMulticastBulkNotificationRequest $request): JsonResponse
    {
        $bulkNotification = $this->bulkNotificationService->sendMulticast($request->toDto());

        return $this->sendResponseForResource(
            BulkNotificationResource::make($bulkNotification),
            'Notification sent successfully.'
        );
    }

    public function list(ListBulkNotificationRequest $request): JsonResponse
    {
        $bulkNotifications = $this->bulkNotificationService->list($request->getCriteria(), $request->getPage(), $request->getOrder());

        return $this->sendResponseForResource(BulkNotificationResource::collection($bulkNotifications));
    }
}
