<?php

namespace Ulams\BulkNotifications\Repositories\Contracts;

use Ulams\BulkNotifications\Models\DeviceToken;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Collection;

interface DeviceTokenRepositoryContract extends BaseRepositoryContract
{
    public function findToken(string $token): ?DeviceToken;

    public function findTokens(): Collection;

    public function findUsersTokens(Collection $userIds): Collection;
}
