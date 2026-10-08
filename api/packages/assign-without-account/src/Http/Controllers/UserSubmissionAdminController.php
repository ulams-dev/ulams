<?php

namespace Ulams\AssignWithoutAccount\Http\Controllers;

use Ulams\AssignWithoutAccount\Dto\UserSubmissionDto;
use Ulams\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use Ulams\AssignWithoutAccount\Http\Controllers\Swagger\UserSubmissionAdminControllerSwagger;
use Ulams\AssignWithoutAccount\Http\Requests\UserSubmissionCreateRequest;
use Ulams\AssignWithoutAccount\Http\Requests\UserSubmissionDeleteRequest;
use Ulams\AssignWithoutAccount\Http\Requests\UserSubmissionListRequest;
use Ulams\AssignWithoutAccount\Http\Requests\UserSubmissionUpdateRequest;
use Ulams\AssignWithoutAccount\Http\Resources\UserSubmissionResource;
use Ulams\AssignWithoutAccount\Services\Contracts\UserSubmissionServiceContract;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Dtos\PaginationDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class UserSubmissionAdminController extends UlamsBaseController implements UserSubmissionAdminControllerSwagger
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
