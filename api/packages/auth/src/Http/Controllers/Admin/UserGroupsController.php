<?php

namespace Ulams\Auth\Http\Controllers\Admin;

use Ulams\Auth\Dtos\UserGroupDto;
use Ulams\Auth\Dtos\UserGroupFilterCriteriaDto;
use Ulams\Auth\Http\Controllers\Admin\Swagger\UserGroupsSwagger;
use Ulams\Auth\Http\Requests\Admin\UserGroupCreateRequest;
use Ulams\Auth\Http\Requests\Admin\UserGroupDeleteRequest;
use Ulams\Auth\Http\Requests\Admin\UserGroupGetRequest;
use Ulams\Auth\Http\Requests\Admin\UserGroupListRequest;
use Ulams\Auth\Http\Requests\Admin\UserGroupMemberAddRequest;
use Ulams\Auth\Http\Requests\Admin\UserGroupMemberRemoveRequest;
use Ulams\Auth\Http\Requests\Admin\UserGroupUpdateRequest;
use Ulams\Auth\Http\Resources\UserGroupDetailedResource;
use Ulams\Auth\Http\Resources\UserGroupResource;
use Ulams\Auth\Http\Resources\UserGroupTreeResource;
use Ulams\Auth\Http\Resources\UserResource;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Exception;
use Illuminate\Http\JsonResponse;

class UserGroupsController extends UlamsBaseController implements UserGroupsSwagger
{
    private UserGroupServiceContract $userGroupService;

    public function __construct(UserGroupServiceContract $userGroupService)
    {
        $this->userGroupService = $userGroupService;
    }

    public function listGroups(UserGroupListRequest $request): JsonResponse
    {
        $filterDto = UserGroupFilterCriteriaDto::instantiateFromRequest($request);
        $paginator = $this->userGroupService->searchAndPaginate($filterDto, $request->except('page'), $request->get('per_page'), $request->get('page'), OrderDto::instantiateFromRequest($request));
        return $this->sendResponseForResource(UserGroupResource::collection($paginator), __('Group list'));
    }

    public function listGroupsTree(UserGroupListRequest $request): JsonResponse
    {
        $filterDto = UserGroupFilterCriteriaDto::instantiateFromRequest($request, true);
        $paginator = $this->userGroupService->searchAndPaginate($filterDto, $request->except('page'), $request->get('per_page'), $request->get('page'));
        return $this->sendResponseForResource(UserGroupTreeResource::collection($paginator), __('Group tree list'));
    }

    public function listWithUsers(UserGroupListRequest $request): JsonResponse
    {
        $filterDto = UserGroupFilterCriteriaDto::instantiateFromRequest($request);
        $paginator = $this->userGroupService->searchAndPaginate($filterDto, $request->except('page'), $request->get('per_page'), $request->get('page'), OrderDto::instantiateFromRequest($request));

        return $this->sendResponseForResource(UserGroupDetailedResource::collection($paginator), __('Group list'));
    }

    public function getGroup(UserGroupGetRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(UserGroupDetailedResource::make($request->getGroupFromRoute()), __('Group details'));
    }

    public function createGroup(UserGroupCreateRequest $request): JsonResponse
    {
        $group = $this->userGroupService->create(UserGroupDto::instantiateFromRequest($request));
        return $this->sendResponseForResource(UserGroupDetailedResource::make($group), __('Group created'));
    }

    public function updateGroup(UserGroupUpdateRequest $request): JsonResponse
    {
        $group = $this->userGroupService->update($request->getGroupFromRoute(), UserGroupDto::instantiateFromRequest($request));
        return $this->sendResponseForResource(UserGroupDetailedResource::make($group), __('Group updated'));
    }

    public function deleteGroup(UserGroupDeleteRequest $request): JsonResponse
    {
        try {
            $deleted = $this->userGroupService->delete($request->getGroupFromRoute());
            if ($deleted) {
                return $this->sendSuccess("Group deleted");
            }
            return $this->sendError("Group not deleted", 422);
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage(), 400);
        }
    }

    public function addMember(UserGroupMemberAddRequest $request): JsonResponse
    {
        $users = $this->userGroupService->addMember($request->getGroupFromRoute(), $request->getUserFromInput());
        return $this->sendResponseForResource(UserResource::collection($users), __('User added to group'));
    }

    public function removeMember(UserGroupMemberRemoveRequest $request): JsonResponse
    {
        $users = $this->userGroupService->removeMember($request->getGroupFromRoute(), $request->getUserFromRoute());
        return $this->sendResponseForResource(UserResource::collection($users), __('User removed from group'));
    }
}
