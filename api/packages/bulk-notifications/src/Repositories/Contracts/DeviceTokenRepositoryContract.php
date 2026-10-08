<?php

namespace EscolaLms\BulkNotifications\Repositories\Contracts;

use EscolaLms\BulkNotifications\Models\DeviceToken;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Collection;

interface DeviceTokenRepositoryContract extends BaseRepositoryContract
{
    public function findToken(string $token): ?DeviceToken;

    public function findTokens(): Collection;

    public function findUsersTokens(Collection $userIds): Collection;
}
