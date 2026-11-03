<?php

namespace Ulams\Auth\Services\Contracts;

use Ulams\Auth\Dtos\UserGroupDto;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Dtos\OrderDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserGroupServiceContract
{
    public function create(UserGroupDto $userGroupDto): Group;

    public function update(Group $group, UserGroupDto $userGroupDto): Group;

    public function delete(Group $group): ?bool;

    public function searchAndPaginate(CriteriaDto $criteriaDto, array $appends = [], ?int $perPage = null, ?int $page = null, ?OrderDto $orderDto = null): LengthAwarePaginator;

    public function getRegisterableGroups(): Collection;

    /**
     * Add member to group and return collection of members
     */
    public function addMember(Group $group, User $user): Collection;

    /**
     * Add several members at once (one query per call, not one per user). Members already in the group stay;
     * `UserAddedToGroup` is fired for each user that was added.
     *
     * @param iterable<User> $users
     * @return int how many were added
     */
    public function addMembers(Group $group, iterable $users): int;

    /**
     * Remove several members at once; `UserRemovedFromGroup` is fired for each user that was a member.
     *
     * @param iterable<User> $users
     * @return int how many were removed
     */
    public function removeMembers(Group $group, iterable $users): int;

    public function addMemberToMultipleGroups(array $groups, User $user): void;

    public function registerMemberToMultipleGroups(array $groups, User $user): void;

    public function addMemberIfGroupIsRegisterable(Group $group, User $user): bool;

    /**
     * Remove member from group and return collection of members
     */
    public function removeMember(Group $group, User $user): Collection;
}
