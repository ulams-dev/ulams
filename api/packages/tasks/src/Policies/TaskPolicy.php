<?php

namespace EscolaLms\Tasks\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\Tasks\Enums\TaskPermissionEnum;
use EscolaLms\Tasks\Models\Task;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    public function listOwn(User $user): bool
    {
        return $user->can(TaskPermissionEnum::LIST_OWN_TASK);
    }

    public function findOwn(User $user, Task $task): bool
    {
        return $user->can(TaskPermissionEnum::FIND_OWN_TASK);
    }

    public function createOwn(User $user): bool
    {
        return $user->can(TaskPermissionEnum::CREATE_OWN_TASK);
    }

    public function updateOwn(User $user, Task $task): bool
    {
        return $user->can(TaskPermissionEnum::UPDATE_OWN_TASK);
    }

    public function deleteOwn(User $user, Task $task): bool
    {
        return $user->can(TaskPermissionEnum::DELETE_OWN_TASK);
    }

    public function list(User $user): bool
    {
        return $user->can(TaskPermissionEnum::LIST_TASK);
    }

    public function find(User $user, Task $task): bool
    {
        return $user->can(TaskPermissionEnum::FIND_TASK);
    }

    public function create(User $user): bool
    {
        return $user->can(TaskPermissionEnum::CREATE_TASK);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can(TaskPermissionEnum::UPDATE_TASK);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can(TaskPermissionEnum::DELETE_TASK);
    }
}
