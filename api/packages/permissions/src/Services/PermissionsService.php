<?php

namespace EscolaLms\Permissions\Services;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Repositories\Criteria\Criterion;
use EscolaLms\Permissions\Dtos\RoleFilterCriteriaDto;
use EscolaLms\Permissions\Events\PermissionRoleChanged;
use EscolaLms\Permissions\Events\PermissionRoleRemoved;
use EscolaLms\Permissions\Services\Contracts\PermissionsServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;
use EscolaLms\Permissions\Exceptions\AdminRoleException;

class PermissionsService implements PermissionsServiceContract
{

    public function listRoles(OrderDto $orderDto, RoleFilterCriteriaDto $criteriaDto): LengthAwarePaginator
    {
        $query = Role::query();

        $query = $this->applyCriteria($query, $criteriaDto->toArray());

        if ($orderDto->getOrderBy()) {
            $query->orderBy($orderDto->getOrderBy(), $orderDto->getOrder() ?? 'asc');
        }

        return $query->paginate();
    }

    public function rolePermissions(string $name): Collection
    {
        $role = Role::where(['name' => $name, 'guard_name' => 'api'])->firstOrFail();
        $rolePermissions = $role->permissions->pluck('name');
        $permission = Permission::where('guard_name', 'api')->get();
        return $permission->map(function ($item) use ($rolePermissions) {
            $item->assigned = $rolePermissions->contains($item->name);
            return $item;
        });
        return $permission;
    }

    public function createRole(string $name): \Spatie\Permission\Contracts\Role
    {
        return Role::findOrCreate(Str::slug($name), 'api');
    }

    public function deleteRole(string $name): bool
    {
        if ($name === 'admin') {
            throw new AdminRoleException("Admin role cannot be deleted");
        }
        $role = Role::where(['name' => $name, 'guard_name' => 'api'])->firstOrFail();
        $roleEvent = clone $role;
        $role->delete();
        event(new PermissionRoleRemoved(auth()->user(), $roleEvent));
        return true;
    }

    public function updateRolePermissions(string $name, array $permissions): Collection
    {
        if ($name === 'admin') {
            throw new AdminRoleException(__("Admin role cannot be updated"));
        }
        $role = $this->createRole($name);
        $role->syncPermissions($permissions);

        event(new PermissionRoleChanged(auth()->user(), $role));
        return $this->rolePermissions($name);
    }

    private function applyCriteria(Builder $query, array $criteria): Builder
    {
        foreach ($criteria as $criterion) {
            if ($criterion instanceof Criterion) {
                $query = $criterion->apply($query);
            }
        }

        return $query;
    }
}
