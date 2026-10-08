<?php

namespace EscolaLms\Notifications\Http\Controllers;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Notifications\Dtos\PageDto;
use EscolaLms\Notifications\Http\Controllers\Swagger\NotificationsApiSwagger;
use EscolaLms\Notifications\Http\Requests\NotificationEventsRequest;
use EscolaLms\Notifications\Http\Requests\NotificationReadAllRequest;
use EscolaLms\Notifications\Http\Requests\NotificationReadRequest;
use EscolaLms\Notifications\Http\Requests\NotificationsRequest;
use EscolaLms\Notifications\Http\Requests\NotificationsUserRequest;
use EscolaLms\Notifications\Http\Resources\NotificationResource;
use EscolaLms\Notifications\Services\Contracts\DatabaseNotificationsServiceContract;
use EscolaLms\Notifications\Dtos\NotificationsFilterCriteriaDto;
use Illuminate\Http\JsonResponse;

class NotificationsController extends EscolaLmsBaseController implements NotificationsApiSwagger
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
