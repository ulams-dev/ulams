<?php

namespace EscolaLms\BulkNotifications\Http\Controllers;

use EscolaLms\BulkNotifications\Http\Controllers\Swagger\BulkNotificationControllerSwagger;
use EscolaLms\BulkNotifications\Http\Requests\ListBulkNotificationRequest;
use EscolaLms\BulkNotifications\Http\Requests\SendUserBulkNotificationRequest;
use EscolaLms\BulkNotifications\Http\Requests\SendMulticastBulkNotificationRequest;
use EscolaLms\BulkNotifications\Http\Resources\BulkNotificationResource;
use EscolaLms\BulkNotifications\Services\Contracts\BulkNotificationServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class BulkNotificationController extends EscolaLmsBaseController implements BulkNotificationControllerSwagger
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
