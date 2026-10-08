<?php

namespace Ulams\Auth\Repositories\Contracts;

use Ulams\Auth\Dtos\UserUpdateInterestsDto;
use Ulams\Auth\Dtos\UserUpdateSettingsDto;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryContract extends BaseRepositoryContract
{
    public function findByEmail(string $email): ?Authenticatable;

    public function findOrCreate(?int $id): Authenticatable;

    public function search(?string $query): LengthAwarePaginator;

    public function patchSettingsUsingDto(Authenticatable $user, UserUpdateSettingsDto $dto): Collection;
    public function putSettingsUsingDto(Authenticatable $user, UserUpdateSettingsDto $keysDto): Collection;
    public function updateSettings(Authenticatable $user, array $settings): Collection;

    public function addInterestById(Authenticatable $user, int $interest_id): Collection;
    public function removeInterestById(Authenticatable $user, int $interest_id): Collection;
    public function updateInterestsUsingDto(Authenticatable $user, UserUpdateInterestsDto $dto): Collection;
    public function updateInterests(Authenticatable $user, array $interests): Collection;

    public function updatePassword(Authenticatable $user, string $newPassword): bool;

    public function findByIdWithRelations(int $id, array $relations = []): ?Authenticatable;
    public function findByEmailOrFail(string $email): ?User;
}
