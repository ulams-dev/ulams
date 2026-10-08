<?php

namespace EscolaLms\AssignWithoutAccount\Http\Controllers;

use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionDto;
use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use EscolaLms\AssignWithoutAccount\Http\Controllers\Swagger\UserSubmissionAdminControllerSwagger;
use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionCreateRequest;
use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionDeleteRequest;
use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionListRequest;
use EscolaLms\AssignWithoutAccount\Http\Requests\UserSubmissionUpdateRequest;
use EscolaLms\AssignWithoutAccount\Http\Resources\UserSubmissionResource;
use EscolaLms\AssignWithoutAccount\Services\Contracts\UserSubmissionServiceContract;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class UserSubmissionAdminController extends EscolaLmsBaseController implements UserSubmissionAdminControllerSwagger
{
    private UserSubmissionServiceContract $userSubmissionService;

    public function __construct(UserSubmissionServiceContract $userSubmissionService)
    {
        $this->userSubmissionService = $userSubmissionService;
    }

    public function index(UserSubmissionListRequest $request): JsonResponse
    {
        $result = $this->userSubmissionService->searchAndPaginate(
            UserSubmissionSearchDto::instantiateFromRequest($request),
            PaginationDto::instantiateFromRequest($request),
            OrderDto::instantiateFromRequest($request),
        );

        return $this->sendResponseForResource(
            UserSubmissionResource::collection($result),
            "User submissions retrieved successfully"
        );
    }

    public function create(UserSubmissionCreateRequest $request): JsonResponse
    {
        $dto = UserSubmissionDto::instantiateFromRequest($request);
        $result = $this->userSubmissionService->create($dto);

        return $this->sendResponseForResource(
            UserSubmissionResource::make($result),
            "User submissions created successfully"
        );
    }

    public function update(UserSubmissionUpdateRequest $request, int $id): JsonResponse
    {
        $dto = UserSubmissionDto::instantiateFromRequest($request);
        $result = $this->userSubmissionService->update($dto, $id);

        return $this->sendResponseForResource(
            UserSubmissionResource::make($result),
            "User submissions updated successfully"
        );
    }

    public function delete(UserSubmissionDeleteRequest $request, int $id): JsonResponse
    {
        $this->userSubmissionService->delete($id);

        return $this->sendSuccess("User submissions deleted successfully");
    }
}
