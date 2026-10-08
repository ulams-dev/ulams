<?php

namespace Ulams\Notifications\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Notifications\Dtos\PageDto;
use Ulams\Notifications\Http\Controllers\Swagger\NotificationsApiSwagger;
use Ulams\Notifications\Http\Requests\NotificationEventsRequest;
use Ulams\Notifications\Http\Requests\NotificationReadAllRequest;
use Ulams\Notifications\Http\Requests\NotificationReadRequest;
use Ulams\Notifications\Http\Requests\NotificationsRequest;
use Ulams\Notifications\Http\Requests\NotificationsUserRequest;
use Ulams\Notifications\Http\Resources\NotificationResource;
use Ulams\Notifications\Services\Contracts\DatabaseNotificationsServiceContract;
use Ulams\Notifications\Dtos\NotificationsFilterCriteriaDto;
use Illuminate\Http\JsonResponse;

class NotificationsController extends UlamsBaseController implements NotificationsApiSwagger
{
    private DatabaseNotificationsServiceContract $service;

    public function __construct(DatabaseNotificationsServiceContract $service)
    {
        $this->service = $service;
    }

    public function index(NotificationsRequest $request): JsonResponse
    {
        $notificationsFilterDto = NotificationsFilterCriteriaDto::instantiateFromRequest($request);
        $pageDto = PageDto::instantiateFromRequest($request);
        $orderDto = OrderDto::instantiateFromRequest($request);

        $notifications = $this->service->getAllNotifications($notificationsFilterDto, $pageDto, $orderDto);

        return $this->sendResponseForResource(NotificationResource::collection($notifications));
    }

    public function user(NotificationsUserRequest $request): JsonResponse
    {
        $notificationsFilterDto = NotificationsFilterCriteriaDto::instantiateFromRequest($request);
        $pageDto = PageDto::instantiateFromRequest($request);
        $orderDto = OrderDto::instantiateFromRequest($request);

        $notifications = $this->service->getUserNotifications($request->getUserFromRoute(), $notificationsFilterDto, $pageDto, $orderDto);

        return $this->sendResponseForResource(NotificationResource::collection($notifications));
    }

    public function events(NotificationEventsRequest $request): JsonResponse
    {
        return $this->sendResponse($this->service->getEvents());
    }

    public function read(NotificationReadRequest $request): JsonResponse
    {
        $notification = $request->getNotification();
        $notification->markAsRead();
        return $this->sendResponseForResource(NotificationResource::make($notification->refresh()));
    }

    public function readAll(NotificationReadAllRequest $request): JsonResponse
    {
        $this->service->markAsReadAll($request->user());
        return $this->sendSuccess(__('All notifications marked as read'));
    }
}
