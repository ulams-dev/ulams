<?php

namespace Ulams\Recommender\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Recommender\Dto\SatisfactionDto;
use Ulams\Recommender\Http\Controllers\Swagger\TermAnalyticControllerContract;
use Ulams\Recommender\Http\Requests\AggregatedFrameListRequest;
use Ulams\Recommender\Http\Requests\SatisfactionSaveRequest;
use Ulams\Recommender\Http\Requests\TermAnalyticListRequest;
use Ulams\Recommender\Http\Requests\TermAnalyticRequest;
use Ulams\Recommender\Http\Resources\AggregatedFrameResource;
use Ulams\Recommender\Http\Resources\ModelAnalyticsResource;
use Ulams\Recommender\Http\Resources\TermAnalyticResource;
use Ulams\Recommender\Services\Contracts\TermAnalyticServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class TermAnalyticController extends UlamsBaseController implements TermAnalyticControllerContract
{
    public function __construct(
        private TermAnalyticServiceContract $termAnalyticService
    ) {}

    public function index(TermAnalyticListRequest $request, string $modelType): JsonResponse
    {
        $terms = $this->termAnalyticService->termAnalyticsList($modelType, $request->getCriteriaDto(), $request->getPaginationDto(), $request->getOrderDto());

        return $this->sendResponseForResource(
            TermAnalyticResource::collection($terms), __('Terms analytics retrieved successfully')
        );
    }

    public function show(TermAnalyticRequest $request, string $modelType, int $modelId, int $id): JsonResponse
    {
        return $this->sendResponseForResource(
            TermAnalyticResource::make($this->termAnalyticService->termAnalytic($modelType, $id), __('Terms analytic retrieved successfully'))
        );
    }

    public function aggregatedFrames(AggregatedFrameListRequest $request, int $id): JsonResponse
    {
        $data = $this->termAnalyticService->aggregatedFrames($id, $request->get('interval'));

        return $this->sendResponseForResource(AggregatedFrameResource::collection($data), __('Aggregated Frames retrieved successfully'));
    }

    public function modelAnalytics(string $modelType, int $modelId): JsonResponse
    {
        $data = $this->termAnalyticService->modelAnalytics($modelType, $modelId);

        return $this->sendResponseForResource(ModelAnalyticsResource::collection($data), __('Model analytics retrieved successfully'));
    }

    public function modelTermAnalytics(TermAnalyticRequest $request, string $modelType, int $modelId, int $term): JsonResponse
    {
        $data = $this->termAnalyticService->modelAnalyticsForTerm($term);

        return $this->sendResponseForResource(ModelAnalyticsResource::make($data), __('Term analytics retrieved successfully'));
    }

    public function satisfaction(SatisfactionSaveRequest $request): \Illuminate\Http\Response
    {
        $dto = SatisfactionDto::instantiateFromRequest($request);

        $this->termAnalyticService->saveSatisfaction($dto);

        return Response::noContent();
    }
}
