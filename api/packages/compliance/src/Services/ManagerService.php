<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Collection;
use Ulams\Compliance\Models\ManagerLink;
use Ulams\Core\Models\User;

class ManagerService
{
    public function managerOf(User|int $user): ?User
    {
        $link = ManagerLink::query()->where('user_id', $user instanceof User ? $user->getKey() : $user)->first();

        return $link ? User::find($link->manager_id) : null;
    }

    /** @return Collection<int, int> ids of the direct reports */
    public function reportIds(User|int $manager): Collection
    {
        return ManagerLink::query()->where('manager_id', $manager instanceof User ? $manager->getKey() : $manager)->pluck('user_id');
    }

    public function link(int $userId, ?int $managerId): ?ManagerLink
    {
        if ($managerId === null) {
            ManagerLink::query()->where('user_id', $userId)->delete();

            return null;
        }
        if ($managerId === $userId) {
            throw new \InvalidArgumentException('A user cannot manage themselves.');
        }

        return ManagerLink::query()->updateOrCreate(['user_id' => $userId], ['manager_id' => $managerId]);
    }
}
