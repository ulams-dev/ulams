<?php

namespace Ulams\StationaryEvents\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\StationaryEvents\Enum\ConstantEnum;
use Ulams\StationaryEvents\Http\Controllers\Swagger\StationaryEventAdminApiSwagger;
use Ulams\StationaryEvents\Http\Requests\CreateStationaryEventRequest;
use Ulams\StationaryEvents\Http\Requests\DeleteStationaryEventRequest;
use Ulams\StationaryEvents\Http\Requests\ListStationaryEventRequest;
use Ulams\StationaryEvents\Http\Requests\ReadStationaryEventRequest;
use Ulams\StationaryEvents\Http\Requests\UpdateStationaryEventRequest;
use Ulams\StationaryEvents\Http\Resources\StationaryEventAdminResource;
use Ulams\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use Illuminate\Http\JsonResponse;

class StationaryEventAdminApiController extends UlamsBaseController implements StationaryEventAdminApiSwagger
{
    private StationaryEventServiceContract $stationaryEventService;

    public function __construct(StationaryEventServiceContract $stationaryEventService)
    {
        $this->stationaryEventService = $stationaryEventService;
    }

    public function index(ListStationaryEventRequest $request): JsonResponse
    {
        $search = $request->except(['limit', 'skip', 'order', 'order_by']);
        $orderDto = OrderDto::instantiateFromRequest($request);

        $stationaryEvents = $this->stationaryEventService
            ->getStationaryEventList($orderDto, $search)
            ->paginate($request->get('per_page') ?? ConstantEnum::PER_PAGE);

        return $this->sendResponseForResource(
            StationaryEventAdminResource::collection($stationaryEvents),
            __('Stationary events retrieved successfully')
        );
    }

    public function store(CreateStationaryEventRequest $request): JsonResponse
    {
        $stationaryEvent = $this->stationaryEventService->create($request->validated());

        return $this->sendResponseForResource(
            StationaryEventAdminResource::make($stationaryEvent),
            __('Stationary event saved successfully')
        );
    }

    public function show(ReadStationaryEventRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(StationaryEventAdminResource::make($request->getStationaryEvent()));
    }

    public function update(UpdateStationaryEventRequest $request): JsonResponse
    {
        $stationaryEvent = $this->stationaryEventService->update($request->getStationaryEvent(), $request->validated());

        return $this->sendResponseForResource(
            StationaryEventAdminResource::make($stationaryEvent),
            __('Stationary event updated successfully')
        );
    }

    public function delete(DeleteStationaryEventRequest $request): JsonResponse
    {
        if (!$this->stationaryEventService->delete($request->getStationaryEvent())) {
            return $this->sendError(__('Error while deleting a stationary event'));
        }

        return $this->sendSuccess(__('Stationary event deleted successfully'));
    }
}
