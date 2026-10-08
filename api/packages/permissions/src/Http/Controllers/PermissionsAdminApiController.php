<?php

namespace Ulams\Permissions\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Permissions\Dtos\RoleFilterCriteriaDto;
use Ulams\Permissions\Events\PermissionRoleChanged;
use Ulams\Permissions\Http\Controllers\Contracts\PermissionsAdminApiContract;
use Ulams\Permissions\Http\Requests\RoleCreateRequest;
use Ulams\Permissions\Http\Requests\RoleDeleteRequest;
use Ulams\Permissions\Http\Requests\RoleListingRequest;
use Ulams\Permissions\Http\Requests\RoleReadRequest;
use Ulams\Permissions\Http\Requests\RoleUpdateRequest;
use Ulams\Permissions\Http\Resources\RoleResource;
use Ulams\Permissions\Http\Resources\PermissionResource;

use Ulams\Permissions\Services\Contracts\PermissionsServiceContract;
use Illuminate\Http\JsonResponse;
use Exception;
use Ulams\Permissions\Exceptions\AdminRoleException;

class PermissionsAdminApiController extends UlamsBaseController implements PermissionsAdminApiContract
{
    private PermissionsServiceContract $service;

    public function __construct(PermissionsServiceContract $service)
    {
        $this->service = $service;
    }

    public function index(RoleListingRequest $request): JsonResponse
    {
        $orderDto = OrderDto::instantiateFromRequest($request);
        $criteriaDto = RoleFilterCriteriaDto::instantiateFromRequest($request);

        $roles = $this->service->listRoles($orderDto, $criteriaDto);
        return $this->sendResponseForResource(RoleResource::collection($roles), "roles list retrieved successfully");
    }

    public function show(RoleReadRequest $request, string $name): JsonResponse
    {
        try {
            $permissions = $this->service->rolePermissions($name);
            return $this->sendResponseForResource(PermissionResource::collection($permissions), "role permissions list retrieved successfully");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function create(RoleCreateRequest $request): JsonResponse
    {
        $role = $this->service->createRole($request->input('name'));
        event(new PermissionRoleChanged(auth()->user(), $role));
        return $this->sendResponseForResource(RoleResource::make($role), "role created successfully");
    }

    public function delete(RoleDeleteRequest $request, string $name): JsonResponse
    {
        try {
            $deleted = $this->service->deleteRole($name);
            return $this->sendResponse($deleted, "role deleted successfully");
        } catch (AdminRoleException $e) {
            return $this->sendError($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function update(RoleUpdateRequest $request, string $name): JsonResponse
    {
        try {
            $permissions = $this->service->updateRolePermissions($name, $request->input('permissions'));
            return $this->sendResponseForResource(PermissionResource::collection($permissions), "role permissions list updated successfully");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
