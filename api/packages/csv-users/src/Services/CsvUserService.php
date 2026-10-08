<?php

namespace Ulams\CsvUsers\Services;

use Ulams\Auth\Dtos\UserFilterCriteriaDto;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Auth\Repositories\Contracts\UserRepositoryContract;
use Ulams\CsvUsers\Events\UlamsImportedNewUserTemplateEvent;
use Ulams\CsvUsers\Services\Contracts\CsvUserServiceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class CsvUserService implements CsvUserServiceContract
{
    private UserRepositoryContract $userRepository;

    public function __construct(UserRepositoryContract $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getDataToExport(UserFilterCriteriaDto $userFilterCriteriaDto): Collection
    {
        return $this->userRepository->searchByCriteria($userFilterCriteriaDto->toArray());
    }

    public function saveUserFromImport(Collection $data, string $returnUrl): Model
    {
        if ($data->get('password')) {
            $data->put('password', Hash::make($data->get('password')));
        }

        if ($user = $this->userRepository->findByEmail($data->get('email'))) {
            $user = $this->userRepository->update($data->toArray(), $user->getKey());
        } else {
            $data->put('is_active', true);
            $user = $this->userRepository->create($data->toArray());
            $user->markEmailAsVerified();
            event(new UlamsImportedNewUserTemplateEvent($user, $returnUrl));
        }

        $user->syncRoles($data->get('roles'));
        $user->syncPermissions($data->get('permissions'));
        $this->syncGroups($user, $data->get('groups') ?? []);

        return $user;
    }

    private function syncGroups(User $user, array $groupNames): void
    {
        foreach ($groupNames as $name) {
            $group = Group::firstOrCreate([
                'name' => $name,
            ]);

            $group->users()->syncWithoutDetaching($user);
        }
    }
}
