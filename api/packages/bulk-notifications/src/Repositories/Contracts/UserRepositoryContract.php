<?php

namespace Ulams\BulkNotifications\Repositories\Contracts;

use Illuminate\Support\Collection;

interface UserRepositoryContract
{
    public function findAllIds(): Collection;
}
