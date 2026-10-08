<?php

namespace Ulams\BulkNotifications\Repositories;

use Ulams\BulkNotifications\Models\DeviceToken;
use Ulams\BulkNotifications\Repositories\Contracts\DeviceTokenRepositoryContract;
use Ulams\Core\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class DeviceTokenRepository extends BaseRepository implements DeviceTokenRepositoryContract
{

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return DeviceToken::class;
    }

    public function findToken(string $token): ?DeviceToken
    {
        /** @var ?DeviceToken */
        return $this->model->newQuery()->where('token', $token)->first();
    }

    public function findTokens(): Collection
    {
        return $this->model
            ->newQuery()
            ->with('user')
            ->get();
    }

    public function findUsersTokens(Collection $userIds): Collection
    {
        return $this->model
            ->newQuery()
            ->whereIn('user_id', $userIds)
            ->with('user')
            ->get();
    }
}
