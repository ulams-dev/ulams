<?php

namespace Ulams\Lrs\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Lrs\Dto\StatementSearchDto;
use Ulams\Lrs\Http\Controllers\Swagger\StatementSwagger;
use Ulams\Lrs\Http\Requests\StatementListRequest;
use Ulams\Lrs\Http\Resources\StatementResource;
use Ulams\Lrs\Services\Contracts\StatementServiceContract;
use Illuminate\Http\JsonResponse;

class StatementController extends UlamsBaseController implements StatementSwagger
{
    private StatementServiceContract $statementService;

    public function __construct(StatementServiceContract $statementService)
    {
        $this->statementService = $statementService;
    }

    public function statements(StatementListRequest $request): JsonResponse
    {
        $results = $this->statementService->searchAndPaginate(
            StatementSearchDto::instantiateFromRequest($request),
            $request->get('per_page') ?? 15
        );

        return $this->sendResponseForResource(StatementResource::make($results), 'Statements retrieved successfully');
    }
}
