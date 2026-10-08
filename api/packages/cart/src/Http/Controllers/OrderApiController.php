<?php

namespace Ulams\Cart\Http\Controllers;

use Ulams\Cart\Http\Requests\OrderSearchRequest;
use Ulams\Cart\Http\Requests\OrderViewRequest;
use Ulams\Cart\Http\Resources\OrderResource;
use Ulams\Cart\Http\Swagger\OrderSwagger;
use Ulams\Cart\Services\Contracts\OrderServiceContract;
use Ulams\Core\Dtos\OrderDto as SortDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class OrderApiController extends UlamsBaseController implements OrderSwagger
{
    protected OrderServiceContract $orderService;

    public function __construct(OrderServiceContract $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(OrderSearchRequest $request): JsonResponse
    {
        $sortDto = SortDto::instantiateFromRequest($request);
        $searchOrdersDto = $request->toDto();
        $paginatedResults = $this->orderService->searchAndPaginateOrders($searchOrdersDto, $sortDto);
        return $this->sendResponseForResource(OrderResource::collection($paginatedResults), __("Your orders history"));
    }

    public function read(OrderViewRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(OrderResource::make($request->getOrder()), __("Order fetched"));
    }
}
