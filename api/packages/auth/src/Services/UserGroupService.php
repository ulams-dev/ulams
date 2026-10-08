<?php

namespace Ulams\Auth\Services;


use Ulams\Auth\Dtos\UserGroupDto;
use Ulams\Auth\Events\UserAddedToGroup;
use Ulams\Auth\Events\UserRemovedFromGroup;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Auth\Repositories\Contracts\UserGroupRepositoryContract;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserGroupService implements UserGroupServiceContract
{
    private UserGroupRepositoryContract $userGroupRepository;

    public function __construct(UserGroupRepositoryContract $userGroupRepository)
    {
        $this->userGroupRepository = $userGroupRepository;
    }

    public function create(UserGroupDto $userGroupDto): Group
    {

        /** @var Group $group */
        $group = $this->userGroupRepository->create($userGroupDto->toArray());
        return $group;
    }

    public function update(Group $group, UserGroupDto $userGroupDto): Group
    {
        $this->userGroupRepository->update($userGroupDto->toArray(), $group->id);
        return $group->refresh();
    }

    public function delete(Group $group): ?bool
    {
        return $this->userGroupRepository->delete($group->getKey());
    }

    public function addMember(Group $group, User $user): Collection
    {
        $group->users()->attach($user->getKey());
        event(new UserAddedToGroup($user));
        return $group->refresh()->users;
    }

    public function addMemberToMultipleGroups(array $groups, User $user): void
    {
        foreach ($groups as $group) {
            if (is_numeric($group)) {
                $group = $this->userGroupRepository->find($group);
            }
            if ($group instanceof Group) {
                $this->addMember($group, $user);
            }
        }
    }

    public function registerMemberToMultipleGroups(array $groups, User $user): void
    {
        foreach ($groups as $group) {
            if (is_numeric($group)) {
                $group = $this->userGroupRepository->find($group);
            }
            if ($group instanceof Group) {
                $this->addMemberIfGroupIsRegisterable($group, $user);
            }
        }
    }

    public function addMemberIfGroupIsRegisterable(Group $group, User $user): bool
    {
        if ($group->registerable) {
            $this->addMember($group, $user);
            return true;
        }
        return false;
    }

    public function removeMember(Group $group, User $user): Collection
    {
        $group->users()->detach($user->getKey());
        event(new UserRemovedFromGroup($user));
        return $group->refresh()->users;
    }

    public function searchAndPaginate(CriteriaDto $criteriaDto, array $appends = [], ?int $perPage = null, ?int $page = null, ?OrderDto $orderDto = null): LengthAwarePaginator
    {
        $query = $this->userGroupRepository->queryWithAppliedCriteria($criteriaDto->toArray())->with('children');

        if ($orderDto) {
            $query = $this->userGroupRepository->orderBy($query, $orderDto);
        }

        if ($perPage === -1 || $page === -1) {
            return $query->paginate($query->count())->appends($appends);
        } else {
            return $query->paginate($perPage, ['*'], 'page', $page)->appends($appends);
        }
    }

    public function getRegisterableGroups(): Collection
    {
        return $this->userGroupRepository->queryWithAppliedCriteria([new EqualCriterion('registerable', true)])->get();
    }
}
