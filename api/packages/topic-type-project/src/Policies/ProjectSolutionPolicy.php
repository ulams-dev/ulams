<?php

namespace EscolaLms\TopicTypeProject\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\TopicTypeProject\Enum\TopicTypeProjectPermissionEnum;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectSolutionPolicy
{
    use HandlesAuthorization;

    public function listOwn(User $user): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::LIST_OWN_PROJECT_SOLUTION);
    }

    public function createOwn(User $user): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::CREATE_OWN_PROJECT_SOLUTION);
    }

    public function deleteOwn(User $user, ProjectSolution $projectSolution): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::DELETE_OWN_PROJECT_SOLUTION)
            && $projectSolution->user_id === $user->getKey();
    }

    public function readOwn(User $user, ProjectSolution $projectSolution): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::READ_OWN_PROJECT_SOLUTION)
            && $projectSolution->user_id === $user->getKey();
    }

    public function read(User $user, ProjectSolution $projectSolution): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::READ_PROJECT_SOLUTION);
    }

    public function update(User $user, ProjectSolution $projectSolution): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::UPDATE_PROJECT_SOLUTION);
    }

    public function delete(User $user, ProjectSolution $projectSolution): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::DELETE_PROJECT_SOLUTION);
    }

    public function list(User $user): bool
    {
        return $user->can(TopicTypeProjectPermissionEnum::LIST_PROJECT_SOLUTION);
    }
}
