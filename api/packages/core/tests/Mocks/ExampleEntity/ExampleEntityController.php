<?php
namespace Ulams\Core\Tests\Mocks\ExampleEntity;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Dtos\PaginationDto;
use Ulams\Core\Dtos\PeriodDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExampleEntityController extends UlamsBaseController
{
    private ExampleEntityService $service;

    public function __construct(ExampleEntityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $orderDto = OrderDto::instantiateFromRequest($request);
        $paginationDto = PaginationDto::instantiateFromRequest($request);
        $searchDto = ExampleEntitySearchDto::instantiateFromRequest($request);

        $results = $this->service->getExampleEntities($searchDto, $paginationDto, $orderDto);

        return new JsonResponse($results);
    }

    public function period(Request $request): JsonResponse
    {
        $periodDto = PeriodDto::instantiateFromRequest($request);

        $results = $this->service->getByPeriod($periodDto);

        return new JsonResponse($results);
    }
}
