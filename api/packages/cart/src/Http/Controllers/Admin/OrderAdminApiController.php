<?php

namespace Ulams\Cart\Http\Controllers\Admin;

use Ulams\Cart\Enums\ExportFormatEnum;
use Ulams\Cart\Exports\OrdersExport;
use Ulams\Cart\Http\Requests\Admin\OrderExportRequest;
use Ulams\Cart\Http\Requests\Admin\OrderSearchRequest;
use Ulams\Cart\Http\Requests\OrderViewRequest;
use Ulams\Cart\Http\Resources\OrderResource;
use Ulams\Cart\Http\Swagger\Admin\OrderAdminSwagger;
use Ulams\Cart\Services\Contracts\OrderServiceContract;
use Ulams\Core\Dtos\OrderDto as SortDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderAdminApiController extends UlamsBaseController implements OrderAdminSwagger
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
        return $this->sendResponseForResource(OrderResource::collection($paginatedResults), __("Order search results"));
    }

    public function read(OrderViewRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(OrderResource::make($request->getOrder()), __("Order fetched"));
    }

    public function export(OrderExportRequest $request): BinaryFileResponse
    {
        $sortDto = SortDto::instantiateFromRequest($request);
        $searchOrdersDto = $request->toDto();
        $result = $this->orderService->searchOrders($searchOrdersDto, $sortDto)->get();
        $format = ExportFormatEnum::fromValue($request->input('format', ExportFormatEnum::CSV));
        return Excel::download(
            new OrdersExport($result),
            $format->getFilename('orders'),
            $format->getWriterType(),
        );
    }
}
