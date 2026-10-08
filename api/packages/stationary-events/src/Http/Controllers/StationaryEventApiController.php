<?php

namespace Ulams\StationaryEvents\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\StationaryEvents\Enum\ConstantEnum;
use Ulams\StationaryEvents\Http\Controllers\Swagger\StationaryEventApiSwagger;
use Ulams\StationaryEvents\Http\Requests\ListStationaryEventForCurrentUserRequest;
use Ulams\StationaryEvents\Http\Requests\ReadStationaryEventPublicRequest;
use Ulams\StationaryEvents\Http\Resources\StationaryEventResource;
use Ulams\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationaryEventApiController extends UlamsBaseController implements StationaryEventApiSwagger
{
    private StationaryEventServiceContract $stationaryEventService;

    public function __construct(StationaryEventServiceContract $stationaryEventService)
    {
        $this->stationaryEventService = $stationaryEventService;
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->except(['limit', 'skip', 'order', 'order_by']);
        $orderDto = OrderDto::instantiateFromRequest($request);

        $stationaryEvents = $this->stationaryEventService
            ->getStationaryEventList($orderDto, $search, true)
            ->paginate($request->get('per_page') ?? ConstantEnum::PER_PAGE);

        return $this->sendResponseForResource(
            StationaryEventResource::collection($stationaryEvents),
            __('Stationary events retrieved successfully')
        );
    }

    public function forCurrentUser(ListStationaryEventForCurrentUserRequest $request): JsonResponse
    {
        $search = $request->except(['limit', 'skip', 'order', 'order_by']);
        $orderDto = OrderDto::instantiateFromRequest($request);

        $stationaryEvents = $this->stationaryEventService
            ->getStationaryEventListForCurrentUser($orderDto, $search)
            ->paginate($request->get('per_page') ?? ConstantEnum::PER_PAGE);

        return $this->sendResponseForResource(
            StationaryEventResource::collection($stationaryEvents),
            __('Stationary events retrieved successfully')
        );
    }

    public function show(ReadStationaryEventPublicRequest $request): JsonResponse
    {
        $stationaryEvent = $request->getStationaryEvent();
        if (!$stationaryEvent->isPublished()) {
            return $this->sendError(__('Stationary events is unpublished'), 400);
        }

        return $this->sendResponseForResource(StationaryEventResource::make($stationaryEvent));
    }
}
