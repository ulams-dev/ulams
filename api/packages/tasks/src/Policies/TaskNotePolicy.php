<?php

namespace EscolaLms\Tasks\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\Tasks\Enums\TaskPermissionEnum;
use EscolaLms\Tasks\Models\TaskNote;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskNotePolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->can(TaskPermissionEnum::CREATE_TASK_NOTE);
    }

    public function update(User $user, TaskNote $taskNote): bool
    {
        return $user->can(TaskPermissionEnum::UPDATE_TASK_NOTE);
    }

    public function delete(User $user, TaskNote $taskNote): bool
    {
        return $user->can(TaskPermissionEnum::DELETE_TASK_NOTE);
    }

    public function createOwn(User $user): bool
    {
        return $user->can(TaskPermissionEnum::CREATE_OWN_TASK_NOTE);
    }

    public function updateOwn(User $user, TaskNote $taskNote): bool
    {
        return $user->can(TaskPermissionEnum::UPDATE_OWN_TASK_NOTE);
    }

    public function deleteOwn(User $user, TaskNote $taskNote): bool
    {
        return $user->can(TaskPermissionEnum::DELETE_OWN_TASK_NOTE);
    }
}
