<?php

namespace Ulams\Permissions\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Permissions\Dtos\RoleFilterCriteriaDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;

/**
 * @package Ulams\Permissions\Http\Services\Contracts
 */
interface PermissionsServiceContract
{
    public function listRoles(OrderDto $orderDto, RoleFilterCriteriaDto $criteriaDto): LengthAwarePaginator;

    public function createRole(string $name): Role;

    public function deleteRole(string $name): bool;

    public function rolePermissions(string $name): Collection;

    public function updateRolePermissions(string $name, array $permissions): Collection;
}
